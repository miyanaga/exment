<?php

namespace Exceedone\Exment\Form\Navbar;

use Illuminate\Contracts\Support\Renderable;

/**
 * Item hidden input
 */
class Hidden implements Renderable
{
    public function render()
    {
        $values = [
            'admin_prefix' => config('admin.route.prefix') ?? '',
            'admin_base_uri' => trim(app('request')->getBaseUrl(), '/') ?? '',
            'admin_uri' => admin_url(),
            'gridrow_select_edit' => config('exment.gridrow_select_edit', 0),
            'gridrow_select_disabled' => config('exment.gridrow_select_disabled', 0),
            'copy_toastr' => exmtrans('common.message.copy_execute'),
        ];

        $html = '';
        foreach ($values as $key => $value) {
            $html .= <<<HTML
            <input type="hidden" id="{$key}" value="{$value}" />
HTML;
        }

        return $html;
    }
}
