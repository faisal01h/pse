import { useEffect, useRef, useState } from 'react';
import { Head, Link } from '@inertiajs/inertia-react';
import { Inertia } from '@inertiajs/inertia';
import CytoscapeComponent from 'react-cytoscapejs';
import SciPol, { SciPolNotice, SciPolTable, SciPolTd, SciPolTh } from '@/Layouts/SciPol';

const STYLESHEET = [
    {
        selector: 'node',
        style: {
            label: 'data(label)',
            'font-size': 11,
            'text-wrap': 'wrap',
            'text-max-width': 96,
            'text-valign': 'bottom',
            'text-margin-y': 6,
            color: '#1b242c',
            'background-color': '#1b242c',
            width: 18,
            height: 18,
        },
    },
    { selector: 'node[kind = "party"]', style: { 'background-color': '#8a3a12' } },
    { selector: 'node[kind = "issue"]', style: { 'background-color': '#1a3a6b' } },
    { selector: 'node[kind = "business"]', style: { 'background-color': '#2f6b3a' } },
    { selector: 'node[kind = "tendency"]', style: { 'background-color': '#6b5200' } },
    {
        selector: 'node[id = "seed"]',
        style: { width: 28, height: 28, 'border-width': 3, 'border-color': '#b45309' },
    },
    {
        selector: 'edge',
        style: {
            label: 'data(label)',
            'font-size': 10,
            'curve-style': 'bezier',
            'target-arrow-shape': 'triangle',
            'line-color': '#1b242c',
            'target-arrow-color': '#1b242c',
            color: '#5c6570',
            width: 1.4,
        },
    },
    { selector: 'edge[?closed]', style: { 'line-style': 'dashed', opacity: 0.6 } },
];

function SuggestField({ type, onPick }) {
    const [value, setValue] = useState('');
    const [items, setItems] = useState([]);
    const [open, setOpen] = useState(false);
    const root = useRef(null);
    const requestId = useRef(0);

    useEffect(() => {
        const close = (event) => {
            if (!root.current?.contains(event.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, []);

    useEffect(() => {
        const query = value.trim();
        if (query.length < 2) {
            setItems([]);
            setOpen(false);
            return undefined;
        }
        const id = requestId.current + 1;
        requestId.current = id;
        const timer = setTimeout(async () => {
            const response = await fetch(
                `${route('scipol.suggest')}?type=${encodeURIComponent(type)}&q=${encodeURIComponent(query)}`,
                { headers: { Accept: 'application/json' } },
            );
            if (requestId.current !== id || !response.ok) {
                return;
            }
            const next = await response.json();
            setItems(next || []);
            setOpen((next || []).length > 0);
        }, 180);
        return () => clearTimeout(timer);
    }, [value, type]);

    return (
        <div ref={root} className="relative">
            <input
                value={value}
                onChange={(event) => setValue(event.target.value)}
                className="w-full border border-ink/30 bg-white px-2 py-1.5 text-sm"
                placeholder={type === 'party' ? 'Search parties…' : 'Search politicians…'}
                autoComplete="off"
            />
            {open ? (
                <ul className="absolute z-20 mt-0.5 max-h-48 w-full overflow-auto border border-ink/30 bg-white shadow">
                    {items.map((item) => (
                        <li key={item.id}>
                            <button
                                type="button"
                                className="block w-full px-2 py-1.5 text-left text-sm hover:bg-[#e8ecf0]"
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    setValue('');
                                    setOpen(false);
                                    onPick(item);
                                }}
                            >
                                {item.name}
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}

function NeighborhoodGraph({ graph }) {
    const elements = [
        ...graph.nodes.map((node) => ({ data: { ...node, closed: false } })),
        ...graph.edges.map((edge) => ({ data: edge })),
    ];

    return (
        <div className="mb-6 border border-ink/25 bg-white">
            <CytoscapeComponent
                elements={elements}
                stylesheet={STYLESHEET}
                layout={{ name: 'breadthfirst', roots: ['seed'], directed: true, padding: 28, spacingFactor: 1.2 }}
                style={{ width: '100%', height: '420px' }}
                cy={(cy) => {
                    cy.removeListener('tap');
                    cy.on('tap', 'node', (event) => {
                        const href = event.target.data('href');
                        if (href) {
                            Inertia.visit(href);
                        }
                    });
                }}
            />
        </div>
    );
}

function Interval({ from, to }) {
    return (
        <span className="text-[12px] text-muted">
            {from || '—'} → {to || 'now'}
        </span>
    );
}

export default function Relationships({
    type = 'politician',
    as_of = null,
    history = false,
    subject = null,
    links = [],
    stances = [],
    tendencies = [],
    affiliations = null,
    history_links = [],
    history_stances = [],
    history_tendencies = [],
    graph = null,
}) {
    const visitSeed = (item) => {
        Inertia.get('/scipol/relationships', {
            type,
            id: item.id,
            as_of: as_of || undefined,
            history: history ? 1 : undefined,
        }, { preserveState: false });
    };

    return (
        <SciPol title="relationship browser">
            <Head title="SciPol · Relationship browser" />
            <h1 className="text-2xl font-bold">Relationship browser</h1>
            <p className="mt-1 mb-4 max-w-2xl text-sm text-muted">
                Start from one published politician or party and walk outward through sourced links, stances, tendencies, and affiliations.
            </p>
            <SciPolNotice>
                The graph is the neighborhood. The tables underneath carry the source, summary, and interval that an edge cannot hold.
            </SciPolNotice>

            <form method="get" action="/scipol/relationships" className="mb-4 grid gap-2 border border-ink/15 bg-white p-3 sm:grid-cols-4">
                <label className="text-sm">
                    <span className="mb-1 block font-bold">Subject type</span>
                    <select name="type" defaultValue={type} className="w-full border border-ink/30 px-2 py-1.5">
                        <option value="politician">Politician</option>
                        <option value="party">Party</option>
                    </select>
                </label>
                <div className="text-sm sm:col-span-2">
                    <span className="mb-1 block font-bold">Find a subject</span>
                    <SuggestField type={type} onPick={visitSeed} />
                    {subject ? <input type="hidden" name="id" value={subject.id} /> : null}
                </div>
                <label className="text-sm">
                    <span className="mb-1 block font-bold">As of</span>
                    <input name="as_of" type="date" defaultValue={as_of || ''} className="w-full border border-ink/30 px-2 py-1.5" />
                </label>
                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" name="history" value="1" defaultChecked={history} />
                    Show closed intervals
                </label>
                <div className="sm:col-span-2 sm:text-right">
                    <button className="bg-ink px-3 py-1.5 text-sm font-bold text-white">Apply</button>
                </div>
            </form>

            {!subject ? (
                <p className="text-sm text-muted">Choose a politician or party to open the neighborhood.</p>
            ) : (
                <>
                    <p className="mb-3 text-sm">
                        Center:{' '}
                        <a href={subject.href} className="font-bold underline">{subject.name}</a>
                        {as_of ? <span className="text-muted"> · as of {as_of}</span> : null}
                    </p>
                    {graph ? <NeighborhoodGraph graph={graph} /> : null}

                    <h2 className="mb-2 text-sm font-bold uppercase tracking-wide text-muted">Links</h2>
                    <RelationshipTable rows={links} empty="No active links in this window." />

                    <h2 className="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-muted">Issue stances</h2>
                    <SciPolTable>
                        <thead>
                            <tr>
                                <SciPolTh>Issue</SciPolTh>
                                <SciPolTh>Position</SciPolTh>
                                <SciPolTh>Interval</SciPolTh>
                                <SciPolTh>Source</SciPolTh>
                            </tr>
                        </thead>
                        <tbody>
                            {stances.length === 0 ? (
                                <tr><SciPolTd colSpan={4}>No stances in this window.</SciPolTd></tr>
                            ) : stances.map((row) => (
                                <tr key={row.id}>
                                    <SciPolTd><Link href={row.issue.href} className="font-bold underline">{row.issue.name}</Link></SciPolTd>
                                    <SciPolTd>{row.position_label}</SciPolTd>
                                    <SciPolTd><Interval from={row.effective_from} to={row.ended_at} /></SciPolTd>
                                    <SciPolTd>{row.source_url ? <a href={row.source_url} className="underline">{row.source_type}</a> : '—'}</SciPolTd>
                                </tr>
                            ))}
                        </tbody>
                    </SciPolTable>

                    <h2 className="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-muted">Tendencies</h2>
                    <SciPolTable>
                        <thead>
                            <tr>
                                <SciPolTh>Axis</SciPolTh>
                                <SciPolTh>Score</SciPolTh>
                                <SciPolTh>Interval</SciPolTh>
                                <SciPolTh>Source</SciPolTh>
                            </tr>
                        </thead>
                        <tbody>
                            {tendencies.length === 0 ? (
                                <tr><SciPolTd colSpan={4}>No tendencies in this window.</SciPolTd></tr>
                            ) : tendencies.map((row) => (
                                <tr key={row.id}>
                                    <SciPolTd>{row.axis_label}</SciPolTd>
                                    <SciPolTd>{row.score}</SciPolTd>
                                    <SciPolTd><Interval from={row.effective_from} to={row.ended_at} /></SciPolTd>
                                    <SciPolTd>{row.source_url ? <a href={row.source_url} className="underline">{row.source_type}</a> : '—'}</SciPolTd>
                                </tr>
                            ))}
                        </tbody>
                    </SciPolTable>

                    {affiliations ? (
                        <>
                            <h2 className="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-muted">Affiliations</h2>
                            <SciPolTable>
                                <thead>
                                    <tr>
                                        <SciPolTh>Business</SciPolTh>
                                        <SciPolTh>Type</SciPolTh>
                                        <SciPolTh>Source</SciPolTh>
                                    </tr>
                                </thead>
                                <tbody>
                                    {affiliations.length === 0 ? (
                                        <tr><SciPolTd colSpan={3}>No published affiliations.</SciPolTd></tr>
                                    ) : affiliations.map((row) => (
                                        <tr key={row.id}>
                                            <SciPolTd><a href={row.business.href} className="font-bold underline">{row.business.name}</a></SciPolTd>
                                            <SciPolTd>{row.label}</SciPolTd>
                                            <SciPolTd>{row.source_url ? <a href={row.source_url} className="underline">{row.source_type}</a> : '—'}</SciPolTd>
                                        </tr>
                                    ))}
                                </tbody>
                            </SciPolTable>
                        </>
                    ) : null}

                    {history ? (
                        <>
                            <h2 className="mb-2 mt-8 text-sm font-bold uppercase tracking-wide text-muted">Closed links</h2>
                            <RelationshipTable rows={history_links} empty="No closed links." />
                            <h2 className="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-muted">Closed stances</h2>
                            <SciPolTable>
                                <thead>
                                    <tr>
                                        <SciPolTh>Issue</SciPolTh>
                                        <SciPolTh>Position</SciPolTh>
                                        <SciPolTh>Interval</SciPolTh>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history_stances.length === 0 ? (
                                        <tr><SciPolTd colSpan={3}>No closed stances.</SciPolTd></tr>
                                    ) : history_stances.map((row) => (
                                        <tr key={row.id}>
                                            <SciPolTd><Link href={row.issue.href} className="font-bold underline">{row.issue.name}</Link></SciPolTd>
                                            <SciPolTd>{row.position_label}</SciPolTd>
                                            <SciPolTd><Interval from={row.effective_from} to={row.ended_at} /></SciPolTd>
                                        </tr>
                                    ))}
                                </tbody>
                            </SciPolTable>
                            <h2 className="mb-2 mt-6 text-sm font-bold uppercase tracking-wide text-muted">Closed tendencies</h2>
                            <SciPolTable>
                                <thead>
                                    <tr>
                                        <SciPolTh>Axis</SciPolTh>
                                        <SciPolTh>Score</SciPolTh>
                                        <SciPolTh>Interval</SciPolTh>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history_tendencies.length === 0 ? (
                                        <tr><SciPolTd colSpan={3}>No closed tendencies.</SciPolTd></tr>
                                    ) : history_tendencies.map((row) => (
                                        <tr key={row.id}>
                                            <SciPolTd>{row.axis_label}</SciPolTd>
                                            <SciPolTd>{row.score}</SciPolTd>
                                            <SciPolTd><Interval from={row.effective_from} to={row.ended_at} /></SciPolTd>
                                        </tr>
                                    ))}
                                </tbody>
                            </SciPolTable>
                        </>
                    ) : null}
                </>
            )}
        </SciPol>
    );
}

function RelationshipTable({ rows, empty }) {
    return (
        <SciPolTable>
            <thead>
                <tr>
                    <SciPolTh>Relation</SciPolTh>
                    <SciPolTh>Other</SciPolTh>
                    <SciPolTh>Interval</SciPolTh>
                    <SciPolTh>Source</SciPolTh>
                </tr>
            </thead>
            <tbody>
                {rows.length === 0 ? (
                    <tr><SciPolTd colSpan={4}>{empty}</SciPolTd></tr>
                ) : rows.map((row) => (
                    <tr key={row.id}>
                        <SciPolTd>{row.label}</SciPolTd>
                        <SciPolTd>
                            <Link href={row.other.browser_href} className="font-bold underline">{row.other.name}</Link>
                        </SciPolTd>
                        <SciPolTd><Interval from={row.effective_from} to={row.ended_at} /></SciPolTd>
                        <SciPolTd>
                            {row.summary ? <div>{row.summary}</div> : null}
                            {row.source_url ? <a href={row.source_url} className="underline">{row.source_type}</a> : null}
                        </SciPolTd>
                    </tr>
                ))}
            </tbody>
        </SciPolTable>
    );
}
