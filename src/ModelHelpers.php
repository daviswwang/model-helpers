<?php
/**
 * Created by PhpStorm.
 * User: fanxinyu
 * Date: 2020-12-25
 * Time: 15:51
 */

declare(strict_types=1);

namespace Daviswwang\ModelHelpers;

//use Daviswwang\ModelHelpers\Exception\ApiException;
use App\Utils\Redis;
use Carbon\Carbon;
use Hyperf\Utils\Arr;
use Hyperf\DbConnection\Db;


trait ModelHelpers
{

    public function data()
    {
        return $this->request->input('0.request', []);
    }

    public function auth()
    {
        return $this->request->input('0.auth', []);
    }

    public function pages()
    {
        return [(int)$this->request->input('0.request.pageSize', 15), ['*'], 'page', (int)$this->request->input('0.request.pageNo', 1)];
    }

    public function mid()
    {
        return $this->request->input('0.auth.shop_code', 'T0001');
    }

    public function userCode()
    {
        return $this->request->input('0.auth.user_code', '');
    }


    public function generateCommonCode()
    {
        $model = new static();
        $data = $model->newQuery()->where('shop_code', $this->mid())->orderByDesc('id')->first();

        if (!$data) {
            $right = '1';
        } else {
            $right = strval(intval(substr($data->contract_code, -6)) + 1);
        }
        //判断当前code是否被占用
        for ($i = 0; true; $i++) {
            $code = $this->mid() . $model::MARK . str_pad($right, 6, '0', STR_PAD_LEFT);
            if (!$model->newQuery()->where('contract_code', $code)->first()) {
                return $code;
            }
            $right += $i;
        }

    }

    public function arrayToObject($arr)
    {
        $arrayToObject = function ($arr) use (&$arrayToObject) {
            if (is_array($arr)) {
                return (object)array_map($arrayToObject, $arr);
            } else {
                return $arr;
            }
        };

        return $arrayToObject($arr);
    }

    public function lock($k, $currentUserId = '')
    {
        $key = 'Lock_' . $k . '_' . $currentUserId;
        $lock = Redis::get($key);
        if (!$lock) {
            Redis::set($key, 'Lock', 10);
            return $key;
        } else {
//            throw new ApiException('10秒内请勿重复提交此用户信息');
        }
    }

    public function whereEmpty($item)
    {
        return function ($query) use ($item) {
            $query->where($item, '');
        };
    }

    public function whereNotEmpty($item)
    {
        return function ($query) use ($item) {
            $query->where($item, '<>', '');
        };
    }

    public function restLesson($quantity, $bespeakQuantity)
    {
        return bcsub(strval($quantity), strval($bespeakQuantity), 0);
    }

    /**
     * 私教返回剩余价值
     * @param $data
     * @return array
     * @throws \Exception
     * @author: fanxinyu
     */
    public function restPrice($data)
    {
        $rest_qty = 0;

        if ($data->quantity > 0) {
            $rest_qty = ($data->quantity - $data->bespeak_qty);

            $rest_price = bcmul(strval($rest_qty), strval($data->fact_price / $data->quantity), 2);
        } elseif ($data->item_type == 2) {
            //按时
            $now = Carbon::now();

//        $endTime = Carbon::instance(new \DateTimeImmutable($data->end_date));
            $endTime = new Carbon($data->end_date);

            $beginTime = new Carbon($data->begin_date);

            $diffDays = $now->diff($endTime)->days;

            $allDay = Carbon::instance($beginTime)->diff($endTime)->days;

            $rest_price = bcmul(strval($diffDays), strval(bcdiv(strval($data->fact_price), strval($allDay), 4)), 2);
        } else {
            $rest_price = 0;
        }

        return [$rest_price, $rest_qty];

    }

    public function only($keys)
    {
        $results = [];

        $input = $this->data();

        $placeholder = new \stdClass();

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            $value = data_get($input, $key, $placeholder);

            if ($value !== $placeholder) {
                Arr::set($results, $key, $value);
            }
        }

        return $results;
    }

    public function getMemberInfoByContract($contractCode, $contract_type = 1)
    {
        return Db::table('ba_member_info')
            ->leftJoin('ba_member_contract', 'ba_member_contract.member_code', '=', 'ba_member_info.member_code')
            ->where('ba_member_contract.contract_code', $contractCode)
            ->where('ba_member_contract.contract_type', $contract_type)
            ->select('ba_member_info.*')
            ->first();
    }
}
