// Unit lookup used by the loan and return forms (scan result).
export async function lookupUnit(code) {
    const trimmed = String(code ?? '').trim();

    if (!trimmed) {
        throw new Error('Isi kode unit lebih dulu.');
    }

    const response = await fetch(`/sales/units/lookup?code=${encodeURIComponent(trimmed)}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (response.status === 404) {
        throw new Error('Kode unit tidak ditemukan.');
    }

    if (!response.ok) {
        throw new Error('Gagal memeriksa unit. Coba lagi.');
    }

    return response.json();
}

export function statusChipClass(status) {
    if (status === 'available') return 'chip-ok';
    if (status === 'on_loan') return 'chip-bad';
    return 'chip-neutral';
}
