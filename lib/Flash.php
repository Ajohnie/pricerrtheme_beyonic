<?php
// make sure sessions work on the page
session_start();

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

    private function getMsg($id, $msg): string
    {
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
    }
}

// if $_SESSION['flash_messages'] isset
// then save them to our class
if (isset($_SESSION['flash_messages'])) {
    Flash::$messages = $_SESSION['flash_messages'];
}

// reset the session's value
$_SESSION['flash_messages'] = array();
