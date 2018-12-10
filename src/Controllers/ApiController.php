<?php

namespace Exceedone\Exment\Controllers;

use Encore\Admin\Facades\Admin;
use Illuminate\Http\Request;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Enums\AuthorityValue;
use Exceedone\Exment\Enums\ColumnType;
use Illuminate\Support\Facades\Auth;

class ApiController extends AdminControllerBase
{
    use ApiTrait;
    public function __construct(Request $request)
    {
        $this->user = Auth::guard('admin_api')->user();
    }
}
