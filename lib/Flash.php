<?php
// make sure sessions work on the page
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class Flash
{

    // where all messages are stored
    public static $messages = array();

    /*
     * A generic function to store flash messages
     *
     * Flash::add('notice', 'a message to display');
     *
     * @param string $name the name/id of the flash
     * @param string $message the message to display
     */
    public static function add($name, $message): void
    {
        $_SESSION['flash_messages'][$name] = $message;
    }

    /*
     * A shortcut to Flash::add()
     *
     * Flash::notice('a message to display');
     */
    public static function __callStatic($fn, $args)
    {
        call_user_func_array(array('Flash', 'add'), array($fn, $args[0]));
    }

    public static function show(): void
    {
        foreach (self::$messages as $id => $msg) {
            echo self::getMsg($id, $msg);
        }
    }

    public static function getAll(): string
    {
        $ret = '';
        foreach (self::$messages as $id => $msg) {
            $ret .= $msg;
        }
        return $ret;
    }

    private function getMsg($id, $msg): string
    {
        // backend alert
        if ($id == 'info') {
            return '<div id="message" class="updated notice is-dismissible rlrsssl-htaccess">
              <p>' . $msg . '</p>
              <button type="button" class="notice-dismiss">
              <span class="screen-reader-text">Dismiss this notice.</span>
              </button>
              </div>';
        }
        if ($id == 'error') {
            return '<div id="message" class="error notice is-dismissible rlrsssl-htaccess">
              <p>' . $msg . '</p>
              <button type="button" class="notice-dismiss">
              <span class="screen-reader-text">Dismiss this notice.</span>
              </button>
              </div>';
        }
        // front end alert
        if ($id == 'fInfo') {
            return '<div class="alert alert-success"><p>' . $msg . '</p></div>';
        }
        if ($id == 'fError') {
            return '<div class="alert alert-danger"><p>' . $msg . '</p></div>';
        }
    }
}

// if $_SESSION['flash_messages'] isset
// then save them to our class
if (isset($_SESSION['flash_messages'])) {
    Flash::$messages = $_SESSION['flash_messages'];
}

// reset the session's value
$_SESSION['flash_messages'] = array();
