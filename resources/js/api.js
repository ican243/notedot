// 서버와 JSON 으로 주고받기. CSRF 토큰을 자동으로 붙이고, 422(입력 오류)면 오류 목록을 돌려준다
export async function api(method, url, body = null) {
    const res = await fetch(url, {
        method,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: body ? JSON.stringify(body) : null,
    });

    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        const error = new Error(data.message || '요청을 처리하지 못했습니다.');
        error.status = res.status;
        error.errors = data.errors || {};
        // 첫 번째 입력 오류 문구를 대표 메시지로
        const first = Object.values(error.errors)[0];
        if (first) error.message = first[0];
        if (res.status === 403) error.message = '권한이 없습니다.';
        if (res.status === 419) error.message = '화면을 연 지 오래되었습니다. 새로고침 후 다시 시도해 주세요.';
        if (res.status === 429) error.message = '요청이 너무 많습니다. 잠시 후 다시 시도해 주세요.';
        throw error;
    }

    return data;
}
