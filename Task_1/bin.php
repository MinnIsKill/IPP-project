<?php
class _var {
    public $name;
    public $type;
    public $value;
    public $frame;

    protected static $vars_arr = array();

    public function __construct($name, $frame){
        $this->name = _var::set_name($name);
        $this->frame = $frame;
    }

    // this function should not be used, as it was created for the purpose of checking whether 
    // a given variable already exists within a given frame and handling the resulting situation. 
    // However, later study found that this check should not be done inside parser, but should be left
    // for the interpreter to handle.
    public static function check_if_exists($searched, $frame) {
        $found = false;
        foreach (self::$vars_arr as $item){
            if (($item == $searched) && ($item->frame == $frame)){
                $found = true;
            }
        }
        return $found;
    }

    function set_name($name) {
        static $cnt = 0;
        $this->name = $name;
        self::$vars_arr[$cnt] = $name;
        //var_dump(self::$vars_arr);
        $cnt++;
    }

    function set_type($type) {
        $this->type = $type;
    }

    function set_value($value) {
        $this->value = $value;
    }

    function set_frame($frame) {
        $this->frame = $frame;
    }

    function get_name() {
        return $this->name;
    }

    function get_type() {
        return $this->type;
    }

    function get_value() {
        return $this->value;
    }

    function get_frame() {
        return $this->frame;
    }
}
?>