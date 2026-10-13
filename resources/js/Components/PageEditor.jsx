import { useMemo, useRef } from 'react';
import { defineBasicExtension } from 'prosekit/basic';
import { createEditor } from 'prosekit/core';
import { ProseKit, useDocChange } from 'prosekit/react';
import 'prosekit/basic/style.css';

function ToolbarButton({ children, onClick, label }) {
    return (
        <button
            type="button"
            aria-label={label}
            className="border-2 border-ink bg-white px-2 py-1 font-bold"
            onMouseDown={(event) => event.preventDefault()}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

function Toolbar({ editor }) {
    const link = () => {
        const href = window.prompt('Alamat tautan');

        if (href) {
            editor.commands.toggleLink({ href });
        }
    };

    return (
        <div className="flex flex-wrap gap-2">
            <ToolbarButton label="Tebal" onClick={() => editor.commands.toggleBold()}>B</ToolbarButton>
            <ToolbarButton label="Miring" onClick={() => editor.commands.toggleItalic()}><span className="italic">I</span></ToolbarButton>
            <ToolbarButton label="Judul" onClick={() => editor.commands.toggleHeading({ level: 2 })}>H2</ToolbarButton>
            <ToolbarButton label="Daftar" onClick={() => editor.commands.toggleList({ kind: 'bullet' })}>Daftar</ToolbarButton>
            <ToolbarButton label="Daftar bernomor" onClick={() => editor.commands.toggleList({ kind: 'ordered' })}>1.</ToolbarButton>
            <ToolbarButton label="Kutipan" onClick={() => editor.commands.toggleBlockquote()}>Kutipan</ToolbarButton>
            <ToolbarButton label="Tautan" onClick={link}>Tautan</ToolbarButton>
        </div>
    );
}

function DocSync({ editor, onChange }) {
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    useDocChange(() => {
        onChangeRef.current(editor.getDocHTML());
    }, { editor });

    return null;
}

export default function PageEditor({ html, onChange }) {
    const editor = useMemo(() => {
        const created = createEditor({ extension: defineBasicExtension() });

        if (html) {
            created.setContent(html);
        }

        return created;
    }, []);

    return (
        <ProseKit editor={editor}>
            <Toolbar editor={editor} />
            <div className="page-editor mt-2">
                <div ref={editor.mount} />
            </div>
            <DocSync editor={editor} onChange={onChange} />
        </ProseKit>
    );
}
