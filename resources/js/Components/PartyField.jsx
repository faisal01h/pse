export default function PartyField({ parties, partyId, partyName, onChange }) {
    return (
        <div>
            <label htmlFor="party_id" className="block font-bold mb-1">Afiliasi partai</label>
            <select
                id="party_id"
                name="party_id"
                value={partyId}
                onChange={(event) => onChange('party_id', event.target.value)}
                className="gov-input"
            >
                <option value="">Tidak terafiliasi</option>
                {parties.map((party) => (
                    <option key={party.id} value={String(party.id)}>{party.name}</option>
                ))}
                <option value="other">Lainnya — ketik sendiri</option>
            </select>
            {partyId === 'other' ? (
                <div className="mt-3">
                    <label htmlFor="party_name" className="block font-bold mb-1">Nama partai</label>
                    <input
                        id="party_name"
                        name="party_name"
                        value={partyName}
                        onChange={(event) => onChange('party_name', event.target.value)}
                        className="gov-input"
                        placeholder="Nama partai yang belum ada di daftar"
                    />
                </div>
            ) : null}
        </div>
    );
}
