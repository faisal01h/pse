import { useState } from 'react';

export default function TopicField({ topics = [], selected = [], onChange }) {
    const [draft, setDraft] = useState('');
    const needle = draft.trim().toLowerCase();
    const chosen = selected.map((name) => name.toLowerCase());
    const suggestions = needle === ''
        ? []
        : topics.filter((name) => name.toLowerCase().includes(needle) && !chosen.includes(name.toLowerCase())).slice(0, 6);

    const add = (name) => {
        const trimmed = name.trim().replace(/\s+/g, ' ');

        if (trimmed === '' || chosen.includes(trimmed.toLowerCase()) || selected.length >= 8) {
            return;
        }

        onChange([...selected, trimmed]);
        setDraft('');
    };

    const remove = (name) => {
        onChange(selected.filter((item) => item.toLowerCase() !== name.toLowerCase()));
    };

    return (
        <div>
            <label htmlFor="topic-draft" className="block font-bold mb-1">Topik</label>
            {selected.length > 0 ? (
                <ul className="mb-3 flex flex-wrap gap-2">
                    {selected.map((name) => (
                        <li key={name} className="flex items-center gap-2 border-2 border-ink bg-white px-2 py-1">
                            <span>{name}</span>
                            <button type="button" className="font-bold" onClick={() => remove(name)} aria-label={`Hapus topik ${name}`}>
                                ×
                            </button>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="mb-3 text-muted">Belum ada topik. Ketik nama topik, lalu tambahkan.</p>
            )}
            <div className="flex flex-col gap-2 sm:flex-row">
                <input
                    id="topic-draft"
                    value={draft}
                    onChange={(event) => setDraft(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            add(draft);
                        }
                    }}
                    className="gov-input"
                    placeholder="cth: lingkungan, subsidi"
                    maxLength={80}
                />
                <button type="button" className="gov-button" onClick={() => add(draft)}>Tambah</button>
            </div>
            {suggestions.length > 0 ? (
                <ul className="mt-2 border-2 border-ink bg-white">
                    {suggestions.map((name) => (
                        <li key={name}>
                            <button type="button" className="block w-full px-3 py-2 text-left hover:bg-canvas" onClick={() => add(name)}>
                                {name}
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
