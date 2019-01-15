<?php

namespace Exceedone\Exment\Items;

use Exceedone\Exment\Items\CustomColumns;
use Encore\Admin\Form\Field;

abstract class CustomItem implements ItemInterface
{
    use ItemTrait;
    
    protected $custom_column;

    protected $options;

    /**
     * laravel-admin set required. if false, always not-set required
     */
    protected $required = true;

    /**
     * Available fields.
     *
     * @var array
     */
    public static $availableFields = [];


    public function __construct($custom_column, $custom_value){
        $this->custom_column = $custom_column;
        $this->label = $this->custom_column->column_view_name;
        $this->setCustomValue($custom_value);
        $this->options = [];
    }

    /**
     * Register custom field.
     *
     * @param string $abstract
     * @param string $class
     *
     * @return void
     */
    public static function extend($abstract, $class)
    {
        static::$availableFields[$abstract] = $class;
    }

    /**
     * get column name
     */
    public function name(){
        return $this->custom_column->column_name;
    }

    /**
     * get Text(for display) 
     */
    public function text(){
        return $this->value;
    }

    /**
     * get html(for display) 
     */
    public function html(){
        // default escapes text
        return esc_html($this->text());
    }

    /**
     * get or set option for convert
     */
    public function options($options = null){
        if(is_null($options)){
            return $this->options;
        }

        $this->options = array_merge(
            $options,
            $this->options
        );

        return $this;
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
        return array_get($custom_value, 'value.'.$this->custom_column->column_name);
    }

    public function getAdminField($form_column = null, $column_name_prefix = null){
        $options = $this->custom_column->options;
        $form_column_options = $form_column->options ?? null;
        // form column name. join $column_name_prefix and $column_name
        $form_column_name = $column_name_prefix.$this->name();
        
        // if hidden setting, add hidden field
        if (boolval(array_get($form_column_options, 'hidden'))) {
            $classname = Field\Hidden::class;
        }else{
            // get field
            $classname = $this->getAdminFieldClass();
        }
        $field = new $classname($form_column_name, [$this->label()]);
        $this->setAdminOptions($field, $form_column_options);

        ///////// get common options
        if (array_key_value_exists('placeholder', $options)) {
            $field->placeholder(array_get($options, 'placeholder'));
        }

        // default
        if (array_key_value_exists('default', $options)) {
            $field->default(array_get($options, 'default'));
        }

        // number_format
        if (boolval(array_get($options, 'number_format'))) {
            $field->attribute(['number_format' => true]);
        }

        // // readonly
        if (boolval(array_get($form_column_options, 'view_only'))) {
            $field->attribute(['readonly' => true]);
        }

        // required
        if (boolval(array_get($options, 'required')) && $this->required) {
            $field->required();
        } else {
            $field->rules('nullable');
        }

        // set validates
        $validate_options = [];
        $validates = $this->getColumnValidates($validate_options);
        // set validates
        if (count($validates)) {
            $field->rules($validates);
        }

        // set help string using result_options
        $help = null;
        $help_regexes = array_get($validate_options, 'help_regexes');
        if (array_key_value_exists('help', $options)) {
            $help = array_get($options, 'help');
        }
        if (isset($help_regexes)) {
            $help .= sprintf(exmtrans('common.help.input_available_characters'), implode(exmtrans('common.separate_word'), $help_regexes));
        }
        if (isset($help)) {
            $field->help(esc_html($help));
        }

        $field->attribute(['data-column_type' => $this->custom_column->column_type]);

        return $field;
    }

    abstract protected function getAdminFieldClass();

    protected function setAdminOptions(&$field, $form_column_options){
    }
    
    protected function setValidates(&$validates){
    }

    public static function getItem(...$args){
        list($custom_column, $custom_value) = $args;
        $column_type = $custom_column->column_type;

        if ($className = static::findItemClass($column_type)) {
            return new $className($custom_column, $custom_value);
        }
        
        admin_error('Error', "Field type [$column_type] does not exist.");

        return null;
    }
    
    /**
     * Find item class.
     *
     * @param string $column_type
     *
     * @return bool|mixed
     */
    public static function findItemClass($column_type)
    {
        $class = array_get(static::$availableFields, $column_type);

        if (class_exists($class)) {
            return $class;
        }

        return false;
    }

    /**
     * Get column validate array.
     * @param string|CustomTable|array $table_obj table object
     * @param string column_name target column name
     * @param array result_options Ex help string, ....
     * @return string
     */
    public function getColumnValidates(&$result_options)
    {
        $custom_table = $this->custom_column->custom_table;
        $custom_column = $this->custom_column;
        $options = array_get($custom_column, 'options');

        $validates = [];
        // setting options --------------------------------------------------
        // unique
        if (boolval(array_get($options, 'unique')) && !boolval(array_get($options, 'multiple_enabled'))) {
            // add unique field
            $unique_table_name = getDBTableName($custom_table); // database table name
            $unique_column_name = "value->".array_get($custom_column, 'column_name'); // column name
            
            $uniqueRules = [$unique_table_name, $unique_column_name];
            // create rules.if isset id, add
            $uniqueRules[] = (isset($value_id) ? "$value_id" : "");
            $uniqueRules[] = 'id';
            // and ignore data deleted_at is NULL 
            $uniqueRules[] = 'deleted_at';
            $uniqueRules[] = 'NULL';
            $rules = "unique:".implode(",", $uniqueRules);
            // add rules
            $validates[] = $rules;
        }

        // // regex rules
        $help_regexes = [];
        if (array_key_value_exists('available_characters', $options)) {
            $available_characters = array_get($options, 'available_characters');
            $regexes = [];
            // add regexes using loop
            foreach ($available_characters as $available_character) {
                switch ($available_character) {
                    case 'lower':
                        $regexes[] = 'a-z';
                        $help_regexes[] = exmtrans('custom_column.available_characters.lower');
                        break;
                    case 'upper':
                        $regexes[] = 'A-Z';
                        $help_regexes[] = exmtrans('custom_column.available_characters.upper');
                        break;
                    case 'number':
                        $regexes[] = '0-9';
                        $help_regexes[] = exmtrans('custom_column.available_characters.number');
                        break;
                    case 'hyphen_underscore':
                        $regexes[] = '_\-';
                        $help_regexes[] = exmtrans('custom_column.available_characters.hyphen_underscore');
                        break;
                    case 'symbol':
                        $regexes[] = '!"#$%&\'()\*\+\-\.,\/:;<=>?@\[\]^_`{}~';
                        $help_regexes[] = exmtrans('custom_column.available_characters.symbol');
                    break;
                }
            }
            if (count($regexes) > 0) {
                $validates[] = 'regex:/^['.implode("", $regexes).']*$/';
            }
        }
        
        // set help_regexes to result_options
        if (count($help_regexes) > 0) {
            $result_options['help_regexes'] = $help_regexes;
        }

        // set column's validates
        $this->setValidates($validates);

        return $validates;
    }
}