export default function BusinessField({ businesses, businessId, businessName, onChange }) {
    return (
        <div>
            <label htmlFor="business_id" className="block font-bold mb-1">Bisnis</label>
            <select
                id="business_id"
                name="business_id"
                value={businessId}
                onChange={(event) => onChange('business_id', event.target.value)}
                className="gov-input"
            >
                <option value="">Pilih bisnis</option>
                {businesses.map((business) => (
                    <option key={business.id} value={String(business.id)}>{business.name}</option>
                ))}
                <option value="other">Lainnya — ketik sendiri</option>
            </select>
            {businessId === 'other' ? (
                <div className="mt-3">
                    <label htmlFor="business_name" className="block font-bold mb-1">Nama bisnis</label>
                    <input
                        id="business_name"
                        name="business_name"
                        value={businessName}
                        onChange={(event) => onChange('business_name', event.target.value)}
                        className="gov-input"
                        placeholder="Nama bisnis yang belum ada di daftar"
                    />
                </div>
            ) : null}
        </div>
    );
}
