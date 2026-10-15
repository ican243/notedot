<?php

namespace App\Services;

use App\Models\Resource;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

// resource(담당자/공간)와 service(상품) 저장 + 서로 연결
// 연결할 id 는 반드시 같은 매장 것만 남긴다 (다른 매장 id 를 끼워 넣는 요청 차단)
class CatalogService
{
    public function __construct(private ScheduleService $schedules) {}

    public function saveResource(Shop $shop, array $data, ?Resource $resource = null): Resource
    {
        return DB::transaction(function () use ($shop, $data, $resource) {
            $isNew = ! $resource;
            $resource ??= $shop->resources()->make();
            $resource->fill(Arr::except($data, 'service_ids'))->save();

            $serviceIds = $shop->services()->whereIn('id', $data['service_ids'] ?? [])->pluck('id');
            $resource->services()->sync($serviceIds);

            // 처음 만들 때는 매장 영업시간을 기본 근무표로
            if ($isNew) {
                $this->schedules->copyShopHours($resource);
            }

            return $resource;
        });
    }

    public function deleteResource(Resource $resource): void
    {
        DB::transaction(function () use ($resource) {
            // 소프트 삭제라 외래키가 동작하지 않으므로 직원 연결은 직접 끊는다
            $resource->staff()->update(['resource_id' => null]);
            $resource->delete();
        });
    }

    public function saveService(Shop $shop, array $data, ?Service $service = null): Service
    {
        return DB::transaction(function () use ($shop, $data, $service) {
            $service ??= $shop->services()->make();
            $service->fill(Arr::except($data, 'resource_ids'))->save();

            $resourceIds = $shop->resources()->whereIn('id', $data['resource_ids'] ?? [])->pluck('id');
            $service->resources()->sync($resourceIds);

            return $service;
        });
    }
}
