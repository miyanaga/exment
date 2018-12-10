<?php


use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Router;
use Exceedone\Exment\Model\CustomTable;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\File;
use Exceedone\Exment\Enums\SystemTableName;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => 'Exceedone\Exment\Controllers',
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'DashboardController@home');
    $router->get('dashboardbox/html/{suuid}', 'DashboardBoxController@getHtml');
    $router->delete('dashboardbox/delete/{suuid}', 'DashboardBoxController@delete');
    $router->resource('dashboard', 'DashboardController');
    $router->get("dashboardbox/table_views", 'DashboardBoxController@tableViews');
    $router->resource('dashboardbox', 'DashboardBoxController');
    $router->resource('auth/menu', 'MenuController', ['except' => ['create']]);
    $router->get('auth/setting', 'AuthController@getSetting');
    $router->put('auth/setting', 'AuthController@putSetting');

    $router->get('system', 'SystemController@index');
    $router->post('system', 'SystemController@post');
    $router->get('system/update', 'SystemController@updatePackage');
    
    $router->get('template', 'TemplateController@index');
    $router->post('template/import', 'TemplateController@import');
    $router->post('template/export', 'TemplateController@export');
    $router->get('template/import', function(){
        return redirect(admin_base_path('template'));
    });
    $router->get('template/export', function(){
        return redirect(admin_base_path('template'));
    });
    
    $router->resource('plugin', 'PluginController', ['except' => ['show']]);
    $router->resource('authority', 'AuthorityController', ['except' => ['show']]);
    $router->resource('table', 'CustomTableController', ['except' => ['show']]);
    $router->resource('loginuser', 'LoginUserController', ['except'=> ['create']]);
    $router->resource('mail', 'MailTemplateController', ['except' => ['show']]);
    $router->get('notify/targetcolumn', 'NotifyController@targetcolumn');
    $router->get('notify/notify_action_target', 'NotifyController@notify_action_target');
    $router->resource('notify', 'NotifyController', ['except' => ['show']]);

    // set static name. because this function is called composer install.
    try {
        if (Schema::hasTable(SystemTableName::CUSTOM_TABLE)) {
            foreach (CustomTable::all()->pluck('table_name') as $value) {
                $router->post("data/{$value}/import", 'CustomValueController@import');
                $router->post("data/{$value}/pluginClick", 'CustomValueController@pluginClick');
                $router->get("data/{$value}/{id}/compare", 'CustomValueController@compare');
                $router->get("data/{$value}/{id}/compareitem", 'CustomValueController@compareitem');
                $router->post("data/{$value}/{id}/compare", 'CustomValueController@restoreRevision');
                $router->post("data/{$value}/{id}/pluginClick", 'CustomValueController@pluginClick');
                $router->post("data/{$value}/{id}/copyClick", 'CustomValueController@copyClick');
                $router->put("data/{$value}/{id}/filedelete", 'CustomValueController@filedelete');
                $router->post("data/{$value}/{id}/fileupload", 'CustomValueController@fileupload');
                $router->resource("data/{$value}", 'CustomValueController');
                $router->resource("column/{$value}", 'CustomColumnController', ['except' => ['show']]);
                $router->resource("form/{$value}", 'CustomFormController', ['except' => ['show']]);
                $router->get("view/{$value}/filter-condition", 'CustomViewController@getFilterCondition');
                $router->resource("view/{$value}", 'CustomViewController', ['except' => ['show']]);
                $router->resource("relation/{$value}", 'CustomRelationController', ['except' => ['show']]);
                $router->resource("copy/{$value}", 'CustomCopyController', ['except' => ['show']]);
                $router->get("navisearch/data/{$value}", 'NaviSearchController@getNaviData');
                $router->post("navisearch/result/{$value}", 'NaviSearchController@getNaviResult');
                $router->get("api/{$value}/query", 'ApiAdminTableController@query');
                $router->get("api/{$value}/relatedLinkage", 'ApiAdminTableController@relatedLinkage');
                $router->post("api/{$value}/{id}", 'ApiAdminTableController@find');
            }
        }
    }catch(\Exception $e){
    }

    $router->get('search', 'SearchController@index');
    $router->post('search/list', 'SearchController@getList');
    $router->post('search/header', 'SearchController@header');
    $router->post('search/relation', 'SearchController@getRelationList');

    $router->get('api/table/{id}', 'ApiController@table');
    $router->get("api/target_table/columns/{id}", 'ApiController@targetBelongsColumns');
    $router->get('api/menu/menutype', 'MenuController@menutype');
    $router->post('api/menu/menutargetvalue', 'MenuController@menutargetvalue');

    $router->get('files/{uuid}', function($uuid){
        return File::downloadFile($uuid);
    });
    $router->delete('files/{uuid}', function($uuid){
        return File::deleteFile($uuid);
    });
});


/**
 * anonymous endpoint
 */
Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => 'Exceedone\Exment\Controllers',
    'middleware'    => ['web', 'admin_anonymous'],
], function (Router $router) {
    $router->get('initialize', 'InitializeController@index');
    $router->post('initialize', 'InitializeController@post');
    $router->get('auth/login', 'AuthController@getLoginExment');
    $router->get('auth/forget', 'ForgetPasswordController@showLinkRequestForm');
    $router->post('auth/forget', 'ForgetPasswordController@sendResetLinkEmail')->name('password.email');
    $router->get('auth/reset/{token}', 'ResetPasswordController@showResetForm');
    $router->post('auth/reset/{token}', 'ResetPasswordController@reset')->name('password.request');

    // get config about login provider
    $login_providers = config('exment.login_providers');
    if(!is_nullorempty($login_providers)){
        $router->get('auth/login/{provider}', 'AuthController@getLoginProvider');
        $router->get('auth/login/{provider}/callback', 'AuthController@callbackLoginProvider');
    }
});


/**
 * api endpoint
 */
 Route::group([
     'prefix' => 'adminapi',
    'namespace'     => 'Exceedone\Exment\Controllers',
    'middleware'    => ['admin_api', 'api'],
], function (Router $router) {
    // set static name. because this function is called composer install.
    try {
        if (Schema::hasTable(SystemTableName::CUSTOM_TABLE)) {
            foreach (CustomTable::all()->pluck('table_name') as $value) {
                $router->get("data/{$value}", 'ApiTableController@list');
                $router->post("data/{$value}", 'ApiTableController@createData');
                $router->put("data/{$value}/{key}", 'ApiTableController@updateData');
                $router->get("data/{$value}/query", 'ApiTableController@query');
                $router->get("data/{$value}/relatedLinkage", 'ApiTableController@relatedLinkage');
                $router->get("data/{$value}/{id}", 'ApiTableController@find');
                $router->post("data/{$value}/{id}", 'ApiTableController@find');
            }
        }
    }catch(\Exception $e){
    }

    $router->get('api/table/{id}', 'ApiController@table');
    $router->get("api/target_table/columns/{id}", 'ApiController@targetBelongsColumns');

});