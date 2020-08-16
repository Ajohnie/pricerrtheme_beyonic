<?php

// make sure sessions work on the page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Flash
{
    public function add($name, $message): void
    {
        $_SESSION['flash_messages'][$name] = $message;
    }

    public function show(): void
    {
        if (isset($_SESSION['flash_messages'])) {
            $all_flash = $_SESSION['flash_messages'];
            if (count($all_flash)) {
                foreach ($all_flash as $id => $msg) {
                    echo $this->getFlashMsg($id, $msg);
                }
            }
        }
    }

    public function getAll(): string
    {
        $ret = '';
        if (isset($_SESSION['flash_messages'])) {
            $all_flash = $_SESSION['flash_messages'];
            if (count($all_flash)) {
                foreach ($all_flash as $id => $msg) {
                    $ret .= $msg;
                }
            }
        }
        return $ret;
    }

    public function getFlashMsg($id, $msg): string
    {
        // backend alert
        if ($id === 'info') {
            return '<div id="message" class="updated notice is-dismissible rlrsssl-htaccess">
              <p>' . $msg . '</p>
              <button type="button" class="notice-dismiss">
              <span class="screen-reader-text">Dismiss this notice.</span>
              </button>
              </div>';
        }
        if ($id === 'error') {
            return '<div id="message" class="error notice is-dismissible rlrsssl-htaccess">
              <p>' . $msg . '</p>
              <button type="button" class="notice-dismiss">
              <span class="screen-reader-text">Dismiss this notice.</span>
              </button>
              </div>';
        }
        // front end alert
        if ($id === 'fInfo') {
            return '<div class="alert alert-success"><p>' . $msg . '</p></div>';
        }
        if ($id === 'fError') {
            return '<div class="alert alert-danger"><p>' . $msg . '</p></div>';
        }
        return '';
    }
}
