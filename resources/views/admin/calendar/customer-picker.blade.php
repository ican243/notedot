{{-- 고객 고르기 (기존 고객 찾기 / 새 고객). 예약 창과 그룹 일정 창이 같이 쓴다 — calendarPage 의 form·customer* 값을 쓴다 --}}
<div class="space-y-2">
    <label class="block text-sm font-medium">{{ term('customer') }}</label>

    <template x-if="form.customer_id">
        <div class="flex items-center justify-between rounded-lg border border-line px-3 py-2.5">
            <span x-text="form.customer_label"></span>
            <button type="button" class="text-sm text-sub underline" @click="clearCustomer()">변경</button>
        </div>
    </template>

    <template x-if="!form.customer_id">
        <div class="space-y-2">
            <div class="flex gap-1 text-sm">
                <button type="button" class="rounded-lg px-3 py-1.5" :class="customerMode === 'search' ? 'bg-text text-white' : 'bg-bg'" @click="customerMode = 'search'">기존 {{ term('customer') }} 찾기</button>
                <button type="button" class="rounded-lg px-3 py-1.5" :class="customerMode === 'new' ? 'bg-text text-white' : 'bg-bg'" @click="customerMode = 'new'">새 {{ term('customer') }}</button>
            </div>
            <div x-show="customerMode === 'search'" class="relative">
                <input type="text" class="input" placeholder="이름 또는 전화번호 뒷자리"
                       x-model="customerQuery" @input.debounce.250ms="searchCustomers()">
                <ul x-show="customerResults.length" class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-line bg-card shadow-lg">
                    <template x-for="c in customerResults" :key="c.id">
                        <li>
                            <button type="button" class="flex w-full justify-between px-3 py-2 text-left text-sm hover:bg-bg" @click="pickCustomer(c)">
                                <span x-text="c.name" class="font-medium"></span>
                                <span class="text-sub" x-text="c.phone + (c.no_show_count ? ' · 노쇼 ' + c.no_show_count : '')"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
            <div x-show="customerMode === 'new'" class="grid gap-2 sm:grid-cols-2">
                <input type="text" class="input" placeholder="이름" x-model="form.customer_name">
                <input type="tel" class="input" placeholder="010-1234-5678" x-model="form.customer_phone">
            </div>
        </div>
    </template>
    <p class="text-sm text-st-noshow" x-show="err('customer_name') || err('customer_phone') || err('customer_id')"
       x-text="err('customer_name') || err('customer_phone') || err('customer_id')"></p>
</div>
