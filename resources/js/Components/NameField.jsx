import { useEffect, useRef, useState } from 'react';

export default function NameField({ value, onChange, onPick }) {
    const [items, setItems] = useState([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const picked = useRef('');
    const root = useRef(null);
    const input = useRef(null);

    useEffect(() => {
        input.current?.focus();
    }, []);

    useEffect(() => {
        if (value === picked.current) {
            return undefined;
        }

        const query = value.trim();
        if (query.length < 2) {
            setItems([]);
            setOpen(false);
            return undefined;
        }

        const timer = setTimeout(async () => {
            const response = await fetch(`${route('reports.suggest')}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                return;
            }
            const next = await response.json();
            setItems(next);
            setActive(0);
            setOpen(next.length > 0);
        }, 180);

        return () => clearTimeout(timer);
    }, [value]);

    useEffect(() => {
        const close = (event) => {
            if (!root.current?.contains(event.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    const choose = (item) => {
        picked.current = item.name;
        onPick(item);
        setOpen(false);
    };

    const onKeyDown = (event) => {
        if (!open || items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((index) => (index + 1) % items.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => (index - 1 + items.length) % items.length);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            choose(items[active]);
        } else if (event.key === 'Escape') {
            setOpen(false);
        }
    };

    return (
        <div ref={root} className="relative">
            <input
                id="name"
                name="name"
                type="text"
                value={value}
                autoComplete="off"
                role="combobox"
                aria-expanded={open}
                aria-controls="name-suggestions"
                aria-activedescendant={open ? `name-option-${active}` : undefined}
                aria-autocomplete="list"
                placeholder="Nama pejabat atau politisi"
                className="gov-input"
                ref={input}
                onChange={(event) => onChange(event.target.value)}
                onFocus={() => items.length > 0 && value !== picked.current && setOpen(true)}
                onKeyDown={onKeyDown}
            />
            {open ? (
                <ul id="name-suggestions" role="listbox" className="absolute z-10 mt-0 w-full border-2 border-ink border-t-0 bg-white">
                    {items.map((item, index) => (
                        <li
                            id={`name-option-${index}`}
                            key={item.id}
                            role="option"
                            aria-selected={index === active}
                            className={`cursor-pointer px-2 py-2 ${index === active ? 'bg-canvas' : ''}`}
                            onMouseEnter={() => setActive(index)}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                choose(item);
                            }}
                        >
                            <span className="font-bold">{item.name}</span>
                            <span className="block text-muted">{item.position} · {item.institution}</span>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
