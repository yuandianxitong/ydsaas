<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use app\service\system\DictionaryService;
use app\tenantapi\validate\v1\system\DictionaryValidate;
use app\tenantapi\validate\v1\system\DictionaryItemValidate;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use think\Response;

class DictionaryController extends Controller
{
    protected DictionaryService $service;

    #[Permission('platform.dictionary.list')]
    public function index(): Response
    {
        $params = $this->getRequestData([
            'keyword' => '',
            'status'  => '',
            'page'    => 1,
            'limit'   => 15,
        ]);
        $result = $this->service->getDictionaryList($params);
        return $this->paginate($result);
    }

    #[Permission('platform.dictionary.list')]
    public function show(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->service->getDictionaryDetail($id);
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('platform.dictionary.create')]
    public function store(): Response
    {
        $data = $this->request->post();
        $this->validate($data, DictionaryValidate::class, [], 'create');
        $result = $this->service->createDictionary($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('platform.dictionary.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, DictionaryValidate::class, [], 'update');
        $this->service->updateDictionary($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('platform.dictionary.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->service->deleteDictionary($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('platform.dictionary.delete')]
    public function batchDelete(): Response
    {
        $ids = $this->request->post('ids', []);
        if (empty($ids)) {
            return $this->error(lang('business.please_select'));
        }
        $this->service->batchDeleteDictionary($ids);
        return $this->success(lang('messages.delete_success'));
    }

    #[PermissionSkip]
    public function options(): Response
    {
        $code = $this->request->get('code', '');
        $items = $this->service->getOptionsByCode($code);
        return $this->success(lang('messages.get_success'), $items);
    }

    #[PermissionSkip]
    public function batchOptions(): Response
    {
        $codesStr = $this->request->get('codes', '');
        $codes = array_filter(explode(',', $codesStr));
        $result = $this->service->getOptionsByCodes($codes);
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('platform.dictionary.list')]
    public function items(): Response
    {
        $id = (int) $this->request->param('id');
        $list = $this->service->getItemList($id);
        return $this->success(lang('messages.get_success'), $list);
    }

    #[Permission('platform.dictionary.create')]
    public function storeItem(): Response
    {
        $data = $this->request->post();
        $this->validate($data, DictionaryItemValidate::class, [], 'create');
        $result = $this->service->createItem($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('platform.dictionary.update')]
    public function updateItem(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, DictionaryItemValidate::class, [], 'update');
        $this->service->updateItem($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('platform.dictionary.delete')]
    public function deleteItem(): Response
    {
        $id = (int) $this->request->param('id');
        $this->service->deleteItem($id);
        return $this->success(lang('messages.delete_success'));
    }
}
