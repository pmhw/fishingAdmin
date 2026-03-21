<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\Product;
use app\model\ProductSku;
use think\db\exception\DbException;
use think\response\Json;

/**
 * 后台 - 公共商品规格（按商品维度配置，用法类似 pond-return-rules?pond_id=）
 */
class ShopProductSkuController extends BaseController
{
    /**
     * 列表 GET /api/admin/shop/product-skus?product_id=1
     */
    public function list(): Json
    {
        $productId = (int) $this->request->get('product_id', 0);
        if ($productId < 1) {
            return json(['code' => 400, 'msg' => '请传入 product_id', 'data' => ['list' => [], 'total' => 0]]);
        }
        if (!Product::find($productId)) {
            return json(['code' => 404, 'msg' => '商品不存在', 'data' => ['list' => [], 'total' => 0]]);
        }

        $rows = ProductSku::where('product_id', $productId)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();
        $list = array_map(fn ($r) => $this->skuToApi($r), $rows->all());

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => ['list' => $list, 'total' => count($list)],
        ]);
    }

    /**
     * 新增 POST /api/admin/shop/product-skus
     */
    public function create(): Json
    {
        $productId = (int) $this->request->post('product_id', 0);
        $specLabel = trim((string) $this->request->post('spec_label', ''));
        if ($productId < 1 || !Product::find($productId)) {
            return json(['code' => 400, 'msg' => '商品不存在', 'data' => null]);
        }
        if ($specLabel === '') {
            return json(['code' => 400, 'msg' => '请填写规格名称', 'data' => null]);
        }

        $sku = ProductSku::create([
            'product_id'     => $productId,
            'spec_label'     => $specLabel,
            'spec_json'      => $this->normalizeSpecJsonForDb($this->request->post('spec_json')),
            'sku_code'       => $this->nullableString($this->request->post('sku_code')),
            'default_price'  => $this->nullableDecimal($this->request->post('default_price')),
            'sort_order'     => (int) $this->request->post('sort_order', 0),
            'status'         => (int) $this->request->post('status', 1),
        ]);

        return json(['code' => 0, 'msg' => '规格已添加', 'data' => $this->skuToApi($sku)]);
    }

    /**
     * 更新 PUT /api/admin/shop/product-skus/:id
     */
    public function update(int $id): Json
    {
        /** @var ProductSku|null $sku */
        $sku = ProductSku::find($id);
        if (!$sku) {
            return json(['code' => 404, 'msg' => '规格不存在', 'data' => null]);
        }
        $data = [];
        $specLabel = $this->request->param('spec_label');
        if ($specLabel !== null && trim((string) $specLabel) !== '') {
            $data['spec_label'] = trim((string) $specLabel);
        }
        if ($this->request->param('spec_json') !== null) {
            $data['spec_json'] = $this->normalizeSpecJsonForDb($this->request->param('spec_json'));
        }
        $skuCode = $this->request->param('sku_code');
        if ($skuCode !== null) {
            $data['sku_code'] = $skuCode === '' ? null : (string) $skuCode;
        }
        $dp = $this->request->param('default_price');
        if ($dp !== null && $dp !== '') {
            $data['default_price'] = round((float) $dp, 2);
        } elseif ($dp === '') {
            $data['default_price'] = null;
        }
        foreach (['sort_order', 'status'] as $f) {
            $v = $this->request->param($f);
            if ($v !== null && $v !== '') {
                $data[$f] = (int) $v;
            }
        }
        if (!empty($data)) {
            $sku->save($data);
            $sku = ProductSku::find($id);
        }

        return json(['code' => 0, 'msg' => '保存成功', 'data' => $this->skuToApi($sku)]);
    }

    /**
     * 删除 DELETE /api/admin/shop/product-skus/:id
     */
    public function delete(int $id): Json
    {
        $sku = ProductSku::find($id);
        if (!$sku) {
            return json(['code' => 404, 'msg' => '规格不存在', 'data' => null]);
        }
        try {
            $sku->delete();
        } catch (DbException $e) {
            return json(['code' => 500, 'msg' => '删除失败：可能已被店铺引用', 'data' => null]);
        }

        return json(['code' => 0, 'msg' => '已删除', 'data' => null]);
    }

    private function skuToApi(ProductSku $s): array
    {
        $a = $s->toArray();
        $a['spec_json'] = $this->decodeSpecJson($a['spec_json'] ?? null);

        return $a;
    }

    private function decodeSpecJson(mixed $v): ?array
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_array($v)) {
            return $v;
        }
        if (is_string($v)) {
            $j = json_decode($v, true);

            return is_array($j) ? $j : null;
        }

        return null;
    }

    private function normalizeSpecJsonForDb(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_array($v)) {
            return json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        if (is_string($v)) {
            $t = trim($v);
            if ($t === '') {
                return null;
            }
            $j = json_decode($t, true);
            if (is_array($j)) {
                return json_encode($j, JSON_UNESCAPED_UNICODE);
            }

            return $t;
        }

        return null;
    }

    private function nullableString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function nullableDecimal(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return round((float) $v, 2);
    }
}
