<?php
class Comity_Test
{
    const EXEC = 0;
    const ASSERT = 1;

    protected $_stack;
    protected $_vars;
    protected $_template;

    function __construct()
    {
        $this->_stack = array();
        $this->_vars = array();
        $this->_template = Minim_Template::getInstance();
        $this->_template->addFunction('exec', '$cmd', '
            $pc = Comity_Test::getInstance("Test");
            $pc->startExec($cmd);
        ');
        $this->_template->addFunction('endExec', '', '
            $pc = Comity_Test::getInstance("Test");
            $pc->endExec();
        ');
        $this->_template->addFunction('assertEquals', '$expr', '
            $pc = Comity_Test::getInstance("Test");
            $pc->assertEquals($expr);
        ');
        $this->_template->addFunction('endAssert', '', '
            $pc = Comity_Test::getInstance("Test");
            $pc->endAssert();
        ');
    }



    static function getInstance($name)
    {
        static $instances = array();
        if (!array_key_exists($name, $instances)) {
            $instances[$name] = new $name();
        }
        return $instances[$name];
    }

    function _handleErrors($flag=TRUE)
    {
        if ($flag) {
            set_error_handler(array($this, '_handleError'));
        } else {
            restore_error_handler();
        }
    }

    function _handleError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        throw new Comity_Exception("$errfile ($errline): $errstr");
    }

    function startExec($cmd)
    {
        array_push($this->_stack, array(self::EXEC, $cmd));
        ob_start();
    }

    function endExec()
    {
        $text = ob_get_contents();
        ob_end_clean();
        list($type, $cmd) = array_pop($this->_stack);
        if ($type != self::EXEC) {
            throw new Comity_Exception(
                "exec must be followed by endExec");
        }
        $cmd = preg_replace(',#TEXT,', '"'.addslashes($text).'"', $cmd);
        $cmd = preg_replace(',\$(\w+),', "\$this->_vars['$1']", $cmd);
        $cmd = preg_replace(',\b(\w+)\(,', "\$this->$1(", $cmd);
        $this->_handleErrors();
        try {
            eval("$cmd;");
            $this->_handleErrors(FALSE);
        } catch (Exception $e) {
            echo '<span class="comity-error" title="'.$e->getMessage().'">'.$text.'</span>';
            $this->_handleErrors(FALSE);
            return;
        }
        echo stripslashes($text);
    }

    function assertEquals($expr)
    {
        array_push($this->_stack, array(self::ASSERT, $expr));
        ob_start();
    }

    function endAssert()
    {
        $text = ob_get_contents();
        ob_end_clean();
        list($type, $expr) = array_pop($this->_stack);
        if ($type != self::ASSERT) {
            throw new Comity_Exception(
                "assert must be followed by endAssert");
        }
        $expr = preg_replace(',#TEXT,', '"'.addslashes($text).'"', $expr);
        $expr = preg_replace(',\$(\w+),', "\$this->_vars[\"$1\"]", $expr);
        $expr = "return ($expr == \"$text\");";
        $this->_handleErrors();
        try {
            $result = eval($expr);
            $this->_handleErrors(FALSE);
        } catch (Exception $e) {
            echo '<span class="comity-fail">', $text, '</span>';
            $this->_handleErrors(FALSE);
            return;
        }
        if (!$result) {
            echo '<span class="comity-fail">', $text, '</span>';
        } else {
            echo '<span class="comity-pass">', $text, '</span>';
        }
    }

    function render($template, $context=array())
    {
        $this->_template->render($template, $context);
    }
}
