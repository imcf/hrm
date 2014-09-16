<?php
// This file is part of the Huygens Remote Manager
// Copyright and license notice: see license.txt

require_once(dirname(__FILE__) . "/inc/User.inc.php");
require_once(dirname(__FILE__) . "/inc/hrm_config.inc.php");
require_once(dirname(__FILE__) . "/inc/Util.inc.php");
require_once(dirname(__FILE__) . "/inc/Validator.inc.php");
require_once(dirname(__FILE__) . "/inc/wiki_help.inc.php");
require_once(dirname(__FILE__) . "/inc/user_mngm/UserManagerFactory.inc.php");

global $email_sender;

/*
 *
 * SANITIZE INPUT
 *   We check the relevant contents of $_POST for validity and store them in
 *   a new array $clean that we will use in the rest of the code.
 *
 *   After this step, only the $clean array and no longer the $_POST array
 *   should be used!
 *
 */

// Here we store the cleaned variables
$clean = array(
    "email" => "",
    "group" => "",
    "pass1" => "",
    "pass2" => "");

// Email
if (isset($_POST["email"])) {
    if (Validator::isEmailValid($_POST["email"])) {
        $clean["email"] = $_POST["email"];
    }
}

// Group name
if (isset($_POST["group"])) {
    if (Validator::isGroupNameValid($_POST["group"])) {
        $clean["group"] = $_POST["group"];
    }
}

// Passwords
if (isset($_POST["pass1"])) {
    if (Validator::isPasswordValid($_POST["pass1"])) {
        $clean["pass1"] = $_POST["pass1"];
    }
}
if (isset($_POST["pass2"])) {
    if (Validator::isPasswordValid($_POST["pass2"])) {
        $clean["pass2"] = $_POST["pass2"];
    }
}

/*
 *
 * END OF SANITIZE INPUT
 *
 */

session_start();

if (isset($_GET['home'])) {
    header("Location: " . "home.php");
    exit();
}

if (!isset($_SESSION['user']) || !$_SESSION['user']->isLoggedIn()) {
    header("Location: " . "login.php");
    exit();
}

if (isset($_SERVER['HTTP_REFERER']) &&
        !strstr($_SERVER['HTTP_REFERER'], 'account')) {
    $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

if (isset($_SESSION['account_user'])) {
    $edit_user = $_SESSION['account_user'];
} else {
    $edit_user = $_SESSION['user'];
}

$message = "";

if (isset($_POST['modify'])) {

    // Set the result to True and then...
    $result = True;

    // ... check that all required entries are indeed set
    // Email
    if ($edit_user->isAdmin()) {
        $emailToUse = '';
    } else {
        if ($clean['email'] == "") {
            $result = False;
            $message = "Please fill in the email field with a valid address";
        } else {
            $emailToUse = $clean['email'];
        }
    }

    // Group
    if ($edit_user->isAdmin()) {
        $groupToUse = '';
    } else {
        if ($clean['group'] == "") {
            $result = False;
            $message = "Please fill in the group field";
        } else {
            $groupToUse = $clean['group'];
        }
    }

    // Passwords
    if ($clean['pass1'] == "" || $clean['pass2'] == "") {
        $result = False;
        $message = "Please fill in both password fields";
    } else {
        if ($clean['pass1'] != $clean['pass2']) {
            $result = False;
            $message = "Passwords do not match";
        } else {
            $passToUse = $clean['pass1'];
        }
    }

    // Update the information in the database
    if ($result == True) {

        // Get the user manager
        $userManager = UserManagerFactory::getUserManager($edit_user->isAdmin());
        $success = $userManager->updateUser($edit_user->isAdmin(), $edit_user->name(),
            $passToUse, $emailToUse, $groupToUse);

        if ($success == True) {
            if (isset($_SESSION['account_user'])) {
                $_SESSION['account_user'] =
                    "Account details successfully modified";
                header("Location: " . "user_management.php");
                exit();
            } else {
                $message = "Account details successfully modified";
                $edit_user->reload();
                $_SESSION['user'] = $edit_user;
                header("Location: " . $_SESSION['referer']);
                exit();
            }
        } else {
            $message = "Database error, please inform the administrator";
        }
    }
}

include("header.inc.php");
?>

<!--
  Tooltips
-->
<span class="toolTip" id="ttSpanCancel">Discard changes and go back to your home page.</span>
<span class="toolTip" id="ttSpanSave">Save the changes.</span>

<div id="nav">
    <div id="navleft">
        <ul>
            <?php
                wiki_link('HuygensRemoteManagerHelpAccount');
            ?>
        </ul>
    </div>
    <div id="navright">
        <ul>
            <?php
                include("./inc/nav/user.inc.php");
                include("./inc/nav/home.inc.php");
            ?>
        </ul>
    </div>
    <div class="clear"></div>
</div>

<div id="content">

    <h3><img alt="SelectImages" src="./images/account_title.png"
              width="40"/>&nbsp;Your account</h3>

    <form method="post" action="" id="useraccount">

        <div id="adduser">
            <?php
            if (isset($_SESSION['account_user']) ||
                !$_SESSION['user']->isAdmin()) {
            ?>
                <label for="email">E-mail address: </label>
<?php
                if ($clean['email'] != "") {
?>
                    <input name="email"
                           id="email"
                           type="text"
                           value="<?php echo $clean['email'] ?>" />
<?php
                } else {
?>
             <input name="email"
                    id="email"
                    type="text"
                    value="<?php echo $edit_user->emailAddress() ?>" />
<?php
                }
?>

            <br />
<?php
            }

            if (isset($_SESSION['account_user']) ||
                !$_SESSION['user']->isAdmin()) {
?>
                <label for="group">Research group: </label>
            <?php
                if ($clean['group'] != "") {
            ?>
                    <input name="group"
                           id="group"
                           type="text"
                           value="<?php echo $clean['group'] ?>" />
            <?php
                } else {
            ?>
                    <input name="group"
                           id="group"
                           type="text"
                           value="<?php echo $edit_user->userGroup() ?>" />
            <?php
                }
            ?>
                <br />
            <?php
            }
            ?>
            <br />
            <label for="pass1">New password: </label>
            <input name="pass1" id="pass1" type="password" />
            <br />
            <label for="pass2">(verify) New password: </label>
            <input name="pass2" id="pass2" type="password" />
            <input name="modify" type="hidden" value="modify" />

            <p>&nbsp;</p>

<?php
            $referer = $_SESSION['referer'];
?>
        </div>

        <div id="controls">
            <input type="button" name="cancel" value=""
                   class="icon cancel"
                   onmouseover="TagToTip('ttSpanCancel' )"
                   onmouseout="UnTip()"
                   onclick="document.location.href='<?php echo $referer ?>'" />
            <input type="button" name="save" value=""
                   class="icon save"
                   onmouseover="TagToTip('ttSpanSave' )"
                   onmouseout="UnTip()"
                   onclick="document.forms['useraccount'].submit()" />
        </div>

    </form>

</div> <!-- content -->

<div id="rightpanel">

    <div id="info">

        <h3>Quick help</h3>

        <p>Please update the account information.</p>

    </div>

    <div id="message">
<?php
            echo "<p>$message</p>";
?>
    </div>

</div> <!-- rightpanel -->

<?php

include("footer.inc.php");

?>
