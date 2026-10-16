{{-- 데모: 계절 전환 버튼으로 고른 계절을 화면이 그려지기 전에 적용 (깜빡임 방지). 브라우저 저장소를 못 쓰면 그냥 넘어간다 --}}
@if (config('demo.enabled'))
    <script>try { var s = localStorage.getItem('notedot.season'); if (['spring', 'summer', 'autumn', 'winter'].indexOf(s) > -1) document.documentElement.dataset.season = s; } catch (e) {}</script>
@endif
