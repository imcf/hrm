#!/bin/bash
#
# Simple (and inefficient) textfile generator for the Huygens Remote Manager
# to be used with Prometheus node_exporter.
#
# Written by Niko Ehrenfeuchter <nikolaus.ehrenfeuchter@unibas.ch>
#

TEXTFILE_DIR="/var/lib/prometheus/node-exporter"
TEXTFILE_NAME="$TEXTFILE_DIR/huygens_rm.prom"
TEMPFILE="$(mktemp huygens_rm.prom.XXXXXXXX)"

set -e
. $(dirname $0)/db_settings.inc.sh


query_database() {
    mysql \
        --user "$db_user" \
        --password="$db_password" \
        --execute="$1" \
        --silent \
        --skip-column-names \
        "$db_name" 2>/dev/null
}


TS_START=$(date +%s.%N)

QUEUE_TOTAL=$(query_database "SELECT COUNT(id) FROM job_queue;")
QUEUE_RUNNING=$(query_database "SELECT COUNT(id) FROM job_queue WHERE status = 'started';")
QUEUE_WAITING=$(query_database "SELECT COUNT(id) FROM job_queue WHERE status = 'queued';")
QUEUE_BROKEN=$(query_database "SELECT COUNT(id) FROM job_queue WHERE status = 'broken';")
QUEUE_KILL=$(query_database "SELECT COUNT(id) FROM job_queue WHERE status = 'kill';")

STATISTICS_TOTAL=$(query_database "SELECT COUNT(id) FROM statistics;")


cat << EOF > "$TEMPFILE"
# HELP huygens_rm_queued_jobs The number of jobs in the HRM (Huygens Remote Manager) queue.
# TYPE huygens_rm_queued_jobs gauge
huygens_rm_queued_jobs{status="total"} $QUEUE_TOTAL
huygens_rm_queued_jobs{status="running"} $QUEUE_RUNNING
huygens_rm_queued_jobs{status="waiting"} $QUEUE_WAITING
huygens_rm_queued_jobs{status="broken"} $QUEUE_BROKEN
huygens_rm_queued_jobs{status="kill"} $QUEUE_KILL

# HELP huygens_rm_statistics Statistics on all finished jobs of this HRM instance.
# TYPE huygens_rm_statistics counter
huygens_rm_statistics{status="total_jobs"} $STATISTICS_TOTAL
EOF

MICROSCOPE_TYPE=$(query_database "SELECT DISTINCT MicroscopeType FROM statistics;" | cut -d ' ' -f 1)
cat << EOF >> "$TEMPFILE"
EOF
for MIC_TYPE in $MICROSCOPE_TYPE ; do
    DESC=$(query_database "SELECT DISTINCT MicroscopeType FROM statistics WHERE MicroscopeType LIKE '$MIC_TYPE%';")
    COUNT=$(query_database "SELECT COUNT(id) FROM statistics WHERE MicroscopeType LIKE '$MIC_TYPE%';")
    echo "huygens_rm_statistics{microscope_type=\"$DESC\"} $COUNT" >> "$TEMPFILE"
done
echo >> "$TEMPFILE"


INPUT_FILE_FORMATS=$(query_database "SELECT DISTINCT ImageFileFormat FROM statistics;")
cat << EOF >> "$TEMPFILE"
# HELP huygens_rm_decon_input_format Deconvolution jobs input file format statistics.
# TYPE huygens_rm_decon_input_format counter
EOF
for FILE_FORMAT in $INPUT_FILE_FORMATS ; do
    COUNT=$(query_database "SELECT COUNT(id) FROM statistics WHERE ImageFileFormat = '$FILE_FORMAT';")
    echo "huygens_rm_decon_input_format{type=\"$FILE_FORMAT\"} $COUNT" >> "$TEMPFILE"
done
echo >> "$TEMPFILE"



cat << EOF >> "$TEMPFILE"
# HELP huygens_rm_decon_user_jobs Deconvolution job statistics by user and microscope type.
# TYPE huygens_rm_decon_user_jobs counter
EOF
query_database "
SELECT
    COUNT(owner) AS jobcount,
    owner,
    MicroscopeType
FROM
    statistics
WHERE
    start >= CURDATE() - INTERVAL 12 MONTH
GROUP BY
    owner, MicroscopeType
ORDER BY
    jobcount DESC;" |
while read RESULT ; do
    COUNT=$(echo "$RESULT" | cut -d '	' -f 1)
    USER=$(echo "$RESULT" | cut -d '	' -f 2)
    TYPE=$(echo "$RESULT" | cut -d '	' -f 3)
    echo "huygens_rm_decon_user_jobs{user=\"$USER\", type=\"$TYPE\"} $COUNT" >> "$TEMPFILE"
done
echo >> "$TEMPFILE"



TS_END=$(date +%s.%N)
DURATION=$(echo "$TS_END - $TS_START" | bc)
cat << EOF >> "$TEMPFILE"
# HELP huygens_rm_collector_duration_seconds Runtime of of the HRM metrics collection script in seconds
# TYPE huygens_rm_collector_duration_seconds gauge
huygens_rm_collector_duration_seconds $DURATION
EOF




# update the Prometheus textfile with the new contents (replacing the file's content
# instead of moving the file there makes it sufficient to have write-permissions to the
# target file instead of the whole directory) and remove the temporary file - updating
# it only once (now) is done to prevent node_exporter from reading half-done files:
cat "$TEMPFILE" > "$TEXTFILE_NAME"
rm "$TEMPFILE"

# if FD 0 is on a terminal, we dump the results (aka "if run interactively, show the output"):
if [ -t 0 ] ; then
    cat "$TEXTFILE_NAME"
fi

