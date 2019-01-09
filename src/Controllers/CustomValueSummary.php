<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use Illuminate\Http\Request;
use Exceedone\Exment\Form\Tools;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Enums\ViewColumnType;
use Exceedone\Exment\Enums\ViewColumnSort;
use Exceedone\Exment\Enums\SystemColumn;
use Exceedone\Exment\Services\Plugin\PluginInstaller;

trait CustomValueSummary
{
    protected function gridSummary()
    {
        $classname = $this->getModelNameDV();
        $grid = new Grid(new $classname);
        PluginInstaller::pluginPreparing($this->plugins, 'loading');

        $this->setSummaryGrid($grid);

        $grid->disableFilter();
        $grid->disableCreateButton();
        $grid->disableActions();
        $grid->disableRowSelector();
        $grid->disableExport();

        $grid->tools(function (Grid\Tools $tools) use ($grid){
            //$tools->append(new Tools\ExportImportButton($this->custom_table->table_name, $grid, true));
            $tools->append(new Tools\GridChangePageMenu('data', $this->custom_table, false));
            $tools->append(new Tools\GridChangeView($this->custom_table, $this->custom_view));
        });

        PluginInstaller::pluginPreparing($this->plugins, 'loaded');
        return $grid;
    }
    protected function setSummaryGrid($grid) {
        // get target table
        $table_name = $this->custom_table->table_name;
        $table_id = getDBTableName($table_name);

        $view = $this->custom_view;
        $query = $grid->model();

        // get join tables
        $relations = CustomRelation::getRelationsByParent($table_name);
        foreach($relations as $relation){
            $child_name = getDBTableName($relation->child_custom_table);
            $query->join($child_name, $table_id.'.id', "$child_name.parent_id");
            $query->where("$child_name.parent_type", $table_name);
        }

        // set filter
        $query = $view->setValueFilter($query, $table_id);
        // // whereはcustom_viewのfilterで実施する
        // $column = new CustomColumn;
        // $query->where($column->getIndexColumnName(), 'aaaa');

        $group_columns = [];
        $select_columns = [];
        $index = 0;
        
        // set grouping columns
        foreach ($view->custom_view_columns as $custom_view_column) {
            $view_column_type = array_get($custom_view_column, 'view_column_type');
            if ($view_column_type == ViewColumnType::COLUMN) {
                $column = $custom_view_column->custom_column;
                if(!isset($column)){
                    continue;
                }
                // get virtual column name
                $column_name = $column->getIndexColumnName();
                $column_view_name = is_nullorempty(array_get($custom_view_column, 'view_column_name'))? 
                    array_get($column, 'column_view_name') : array_get($custom_view_column, 'view_column_name');

                $group_columns[] = $column_name;
                $select_columns[] = "$column_name as column_$index";

                $grid->column("column_$index", $column_view_name)->sortable()->display(function ($v) use ($column, $index) {
                    if (is_null($this)) {
                        return '';
                    }
                    $val = array_get($this, "column_$index");
                    return esc_html($this->editValue($column, $val, true));
                });
                $index++;
            }
            elseif ($view_column_type == ViewColumnType::SYSTEM) {
                $system_info = $form_column_name = SystemColumn::getOption(['id' => array_get($custom_view_column, 'view_column_target_id')]);
                $view_column_target = array_get($system_info, 'name');
                $view_column_id = ($system_info['type'] == 'user')? 
                    $view_column_target . '_id': $view_column_target;
                $column_view_name = exmtrans("common.$view_column_target");

                $group_columns[] = "$table_id.$view_column_id";
                $select_columns[] = "$table_id.$view_column_id";

                // get column name
                $grid->column($view_column_target, $column_view_name)->sortable()
                    ->display(function ($value) use ($view_column_target) {
                        if (!is_null($value)) {
                            return esc_html($value);
                        }
                        // if cannnot get value, return array_get from this
                        return esc_html(array_get($this, $view_column_target));
                    });
            }
        }
        // set summary columns
        foreach ($view->custom_view_summaries as $custom_view_summary) {
            $column_id = $custom_view_summary->view_column_target_id;
            $column = CustomColumn::find($column_id);
            if (!isset($column)) {
                continue;
            }
            $column_table_name = getDBTableName($column->custom_table);
            $column_name = $column->column_name;
            $column_view_name = is_nullorempty(array_get($custom_view_summary, 'view_column_name'))? 
                array_get($column, 'column_view_name') : array_get($custom_view_summary, 'view_column_name');

            $summary = 'sum';
            switch($custom_view_summary->view_summary_condition) {
                case 1:
                    $summary = 'sum';
                    break;
                case 2:
                    $summary = 'avg';
                    break;
                case 3:
                    $summary = 'count';
                    break;
            }
            $select_columns[] = \DB::raw("$summary($column_table_name.value->'$.$column_name') AS column_$index");

            $grid->column("column_$index", $column_view_name)->sortable()->display(function ($v) use ($column, $index) {
                if (is_null($this)) {
                    return '';
                }
                $val = array_get($this, "column_$index");
                return esc_html($this->editValue($column, $val, true));
            });
            $index++;
        }
 
        // set sql select columns
        $query->select($select_columns);
 
        // set sql grouping columns
        $query->groupBy($group_columns);
    }
}
