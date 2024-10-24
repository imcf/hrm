#!/bin/bash
#

CONF="$(dirname $0)/db_settings"

if [ -r "$CONF" ] ; then
    # echo "Using '$CONF'..."
    . "$CONF"
else


    CONF="$(dirname $0)../../config/hrm_config.inc"

    if ! [ -r "$CONF" ] ; then
        echo "ERROR: cannot read DB connection settings from file '$CONF'!"
        exit 100
    fi

    # echo "Using '$CONF'..."
    # parse the DB settings:
    # eval $(grep '^\$db_' "$CONF" | sed 's,^ *\$,, ; s, ,,g')
    grep '^\$db_' "$CONF" | sed 's,^ *\$,, ; s, ,,g'
fi

