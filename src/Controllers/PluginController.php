<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
//use Encore\Admin\Controllers\HasResourceActions;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Plugin;
use Exceedone\Exment\Services\Plugin\PluginInstaller;
use Exceedone\Exment\Enums\PluginType;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use File;

class PluginController extends AdminControllerBase
{
    use HasResourceActions;

    public function __construct(Request $request)
    {
        $this->setPageInfo(exmtrans("plugin.header"), exmtrans("plugin.header"), exmtrans("plugin.description"), 'fa-plug');
    }

    /**
     * Index interface.
     *
     * @return Content
     */
    /**
     * Index interface.
     *
     * @return Content
     */
    public function index(Request $request, Content $content)
    {
        $this->AdminContent($content);
        $content->row(view('exment::plugin.upload'));
        $content->body($this->grid());
        return $content;
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new Plugin);
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            $filter->like('uuid', exmtrans("plugin.uuid"));
            $filter->like('plugin_name', exmtrans("plugin.plugin_name"));
        });

        $grid->column('plugin_name', exmtrans("plugin.plugin_name"))->sortable();
        $grid->column('plugin_view_name', exmtrans("plugin.plugin_view_name"))->sortable();
        $grid->column('plugin_types', exmtrans("plugin.plugin_type"))->displayEscape(function ($plugin_types) {
            return implode(exmtrans('common.separate_word'), collect($plugin_types)->map(function ($plugin_type) {
                return PluginType::getEnum($plugin_type)->transKey("plugin.plugin_type_options") ?? null;
            })->toArray());
        })->sortable();
        $grid->column('author', exmtrans("plugin.author"));
        $grid->column('version', exmtrans("plugin.version"));
        $grid->column('active_flg', exmtrans("plugin.active_flg"))->displayEscape(function ($active_flg) {
            return boolval($active_flg) ? exmtrans("common.available_true") : exmtrans("common.available_false");
        });

        $grid->disableCreateButton();
        $grid->disableExport();
        
        $grid->actions(function ($actions) {
            $actions->disableView();
        });
        return $grid;
    }

    //Function use to upload file and update or add new record
    protected function store(Request $request)
    {
        //Check file existed in Request
        if ($request->hasfile('fileUpload')) {
            return PluginInstaller::uploadPlugin($request->file('fileUpload'));
        }
        // if not exists, return back and message
        return back()->with('errorMess', exmtrans("plugin.help.errorMess"));
    }

    //Delete record from database (one or multi records)
    protected function destroy($id)
    {
        $this->deleteFolder($id);
        if ($this->form($id, true)->destroy($id)) {
            return response()->json([
                'status' => true,
                'message' => trans('admin.delete_succeeded'),
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => trans('admin.delete_failed'),
            ]);
        }
    }

    //Delete one or multi folder corresponds to the plugins
    protected function deleteFolder($id)
    {
        $idlist = explode(",", $id);
        foreach ($idlist as $id) {
            $plugin = Plugin::getEloquent($id);
            if (!isset($plugin)) {
                continue;
            }

            // get disk
            $disk = \Storage::disk(Define::DISKNAME_ADMIN);
            $folder = $plugin->getPath();
            if ($disk->exists($folder)) {
                $disk->deleteDirectory($folder);
            }
        }
    }

    //Check request when edit record to delete null values in event_triggers
    protected function update(Request $request, $id)
    {
        if (isset($request->get('options')['event_triggers']) === true) {
            $event_triggers = $request->get('options')['event_triggers'];
            $options = $request->get('options');
            $event_triggers = array_filter($event_triggers, 'strlen');
            $options['event_triggers'] = $event_triggers;
            $request->merge(['options' => $options]);
        }
        return $this->form($id)->update($id);
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form($id = null, $isDelete = false)
    {
        $plugin = Plugin::getEloquent($id);

        // create form
        $form = new Form(new Plugin);
        $form->display('uuid', exmtrans("plugin.uuid"));
        $form->display('plugin_name', exmtrans("plugin.plugin_name"));
        $form->display('plugin_view_name', exmtrans("plugin.plugin_view_name"));
        // create as label
        $form->display('plugin_types', exmtrans("plugin.plugin_type"))->with(function ($plugin_types) {
            return implode(exmtrans('common.separate_word'), collect($plugin_types)->map(function ($plugin_type) {
                return PluginType::getEnum($plugin_type)->transKey("plugin.plugin_type_options") ?? null;
            })->toArray());
        });
        $form->display('author', exmtrans("plugin.author"));
        $form->display('version', exmtrans("plugin.version"));
        $form->switch('active_flg', exmtrans("plugin.active_flg"));
        $form->embeds('options', exmtrans("plugin.options.header"), function ($form) use ($plugin) {
            if ($plugin->matchPluginType([PluginType::TRIGGER, PluginType::DOCUMENT, PluginType::IMPORT, PluginType::VALIDATOR])) {
                $form->multipleSelect('target_tables', exmtrans("plugin.options.target_tables"))->options(function ($value) {
                    $options = CustomTable::filterList()->pluck('table_view_name', 'table_name')->toArray();
                    return $options;
                })->help(exmtrans("plugin.help.target_tables"));
                // only trigger
                if ($plugin->matchPluginType(PluginType::TRIGGER)) {
                    $form->multipleSelect('event_triggers', exmtrans("plugin.options.event_triggers"))->options(function ($value) {
                        return getTransArray(Define::PLUGIN_EVENT_TRIGGER, "plugin.options.event_trigger_options");
                    })->help(exmtrans("plugin.help.event_triggers"));
                }
            } elseif ($plugin->matchPluginType(PluginType::API)) {
                // Plugin_type = 'api'
                $form->text('uri', exmtrans("plugin.options.uri"))->required();
            } elseif ($plugin->matchPluginType(PluginType::PAGE)) {
                // Plugin_type = 'page'
                $form->icon('icon', exmtrans("plugin.options.icon"))->help(exmtrans("plugin.help.icon"));
                $form->text('uri', exmtrans("plugin.options.uri"))->required();
            } elseif ($plugin->matchPluginType(PluginType::BATCH)) {
                $form->number('batch_hour', exmtrans("plugin.options.batch_hour"))
                    ->help(exmtrans("plugin.help.batch_hour") . sprintf(exmtrans("common.help.task_schedule"), getManualUrl('quickstart_more#'.exmtrans('common.help.task_schedule_id'))))
                    ->default(3);
                    
                $form->text('batch_cron', exmtrans("plugin.options.batch_cron"))
                    ->help(exmtrans("plugin.help.batch_cron") . sprintf(exmtrans("common.help.task_schedule"), getManualUrl('quickstart_more#'.exmtrans('common.help.task_schedule_id'))))
                    ->rules('max:100');
            }

            if ($plugin->matchPluginType([PluginType::TRIGGER, PluginType::DOCUMENT])) {
                $form->text('label', exmtrans("plugin.options.label"));
                $form->icon('icon', exmtrans("plugin.options.icon"))->help(exmtrans("plugin.help.icon"));
                $form->text('button_class', exmtrans("plugin.options.button_class"))->help(exmtrans("plugin.help.button_class"));
            }
        })->disableHeader();

        if (!$isDelete) {
            $this->setCustomOptionForm($plugin, $form);
        }

        $form->tools(function (Form\Tools $tools) use ($plugin) {
            if ($plugin->matchPluginType(PluginType::PAGE)) {
                $tools->append(view('exment::tools.button', [
                    'href' => admin_url($plugin->getRouteUri()),
                    'label' => exmtrans('plugin.show_plugin_page'),
                    'icon' => 'fa-desktop',
                    'btn_class' => 'btn-purple',
                ]));
            }
        });
        
        $form->disableReset();
        return $form;
    }

    /**
     * Get plugin custom option
     *
     * @param [type] $plugin
     * @return void
     */
    protected function setCustomOptionForm($plugin, &$form)
    {
        if (!isset($plugin)) {
            return;
        }
        
        $pluginClass = $plugin->getClass(null, ['throw_ex' => false, 'as_setting' => true]);
        if (!isset($pluginClass)) {
            return;
        }
        
        if (!$pluginClass->useCustomOption()) {
            return;
        }

        $form->embeds('custom_options', exmtrans("plugin.options.custom_options_header"), function ($form) use ($pluginClass) {
            $pluginClass->setCustomOptionForm($form);
        });
    }
}
