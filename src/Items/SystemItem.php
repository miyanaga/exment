<?php

namespace Exceedone\Exment\Items;

class SystemItem implements ItemInterface
{
    use ItemTrait;
    
    protected $column_name;

    public function __construct($column_name, $custom_value){
        $this->column_name = $column_name;
        $this->value = $this->getTargetValue($custom_value);
        $this->label = exmtrans("common.$this->column_name");
    }

    /**
     * get column name
     */
    public function name(){
        return $this->column_name;
    }

    /**
     * get text(for display) 
     */
    public function text(){
        return $this->value;
    }

    /**
     * get html(for display) 
     * *this function calls from non-escaping value method. So please escape if not necessary unescape. 
     */
    public function html(){
        return esc_html($this->text());
    }

    public function setCustomValue($custom_value){
        $this->value = $this->getTargetValue($custom_value);
        if(isset($custom_value)){
            $this->id = $custom_value->id;
        }

        $this->prepare();
        
        return $this;
    }

    protected function getTargetValue($custom_value){
        return array_get($custom_value, $this->column_name);
    }   
    
    public static function getItem(...$args){
        list($column_name, $custom_value) = $args;
        return new self($column_name, $custom_value);
    }
}