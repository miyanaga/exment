<?php

namespace Exceedone\Exment\Model;

class CustomFormPriority extends ModelBase
{
    use Traits\ClearCacheTrait;

    protected $guarded = ['id'];
    protected $appends = ['form_priority_text'];

    public function custom_form()
    {
        return $this->belongsTo(CustomForm::class, 'custom_form_id');
    }

    public function custom_form_priority_conditions()
    {
        return $this->morphMany(Condition::class, 'morph', 'morph_type', 'morph_id');
    }

    /**
     * check if custom_value and user(organization, role) match for conditions.
     */
    public function isMatchCondition($custom_value)
    {
        foreach ($this->custom_form_priority_conditions as $condition) {
            if (!$condition->isMatchCondition($custom_value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * get filter condition text for grid.
     */
    public function getFormPriorityTextAttribute()
    {
        if (isset($this->custom_form_priority_conditions)) {
            $list =[];
            foreach ($this->custom_form_priority_conditions as $condition) {
                $list[] = $condition->condition_text;
            }
            return implode(' | ', $list);
        }
        return '';
    }
    
    public function deletingChildren()
    {
        $this->custom_form_priority_conditions()->delete();
    }

    protected static function boot()
    {
        parent::boot();
        
        static::deleting(function ($model) {
            $model->deletingChildren();
        });
    }
}
