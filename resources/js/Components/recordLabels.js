export const categoryLabel = {
    statement: 'Pernyataan publik',
    allegation: 'Dugaan pelanggaran',
    conviction: 'Vonis pengadilan',
};

export const categoryClass = {
    statement: 'border-brand text-brand',
    allegation: 'border-ink text-ink',
    conviction: 'border-ink bg-ink text-white',
};

export function formatRecordDate(value) {
    if (!value) {
        return 'Tanggal tidak diisi';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
