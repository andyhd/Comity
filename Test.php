<?php
class Comity_Test
{
    const EXEC = 0;
    const ASSERT = 1;

    var $stack;
    var $vars;

    function __construct()
    {
        $this->stack = array();
        $this->vars = array();
    }

    static function getInstance($name)
    {
        static $instances = array();
        if (!array_key_exists($name, $instances)) {
            $instances[$name] = new $name();
        }
        return $instances[$name];
    }

    function startExec($cmd)
    {
        array_push($this->stack, array(self::EXEC, $cmd));
        ob_start();
    }

    function endExec()
    {
        $text = ob_get_contents();
        ob_end_clean();
        list($type, $cmd) = array_pop($this->stack);
        if ($type != self::EXEC) {
            throw new Comity_Exception(
                "exec must be followed by endExec");
        }
        $cmd = preg_replace(',#TEXT,', '"'.addslashes($text).'"', $cmd);
        $cmd = preg_replace(',\$(\w+),', "\$this->vars['$1']", $cmd);
        $cmd = preg_replace(',\b(\w+)\(,', "\$this->$1(", $cmd);
        try {
            eval("$cmd;");
        } catch (Exception $e) {
            echo '<span class="comity-error">'.$text.'</span>';
            return;
        }
        echo stripslashes($text);
    }

    function assertEquals($expr)
    {
        array_push($this->stack, array(self::ASSERT, $expr));
        ob_start();
    }

    function endAssert()
    {
        $text = ob_get_contents();
        ob_end_clean();
        list($type, $expr) = array_pop($this->stack);
        if ($type != self::ASSERT) {
            throw new Comity_Exception(
                "assert must be followed by endAssert");
        }
        $expr = preg_replace(',#TEXT,', '"'.addslashes($text).'"', $expr);
        $expr = preg_replace(',\$(\w+),', "\$this->vars[\"$1\"]", $expr);
        $expr = "return ($expr == \"$text\");";
        try {
            $result = eval($expr);
        } catch (Exception $e) {
            echo '<span class="comity-error">', $text, '</span>';
            return;
        }
        if (!$result) {
            echo '<span class="comity-fail">', $text, '</span>';
        } else {
            echo '<span class="comity-pass">', $text, '</span>';
        }
    }
}
