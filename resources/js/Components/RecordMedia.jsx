export default function RecordMedia({ attachments, compact = false }) {
    if (!attachments?.length) {
        return null;
    }

    return (
        <ul className={compact ? 'flex flex-wrap gap-3' : 'mt-3 flex flex-col gap-4'}>
            {attachments.map((file) => (
                <li key={file.id}>
                    {file.kind === 'image' ? (
                        <img src={file.url} alt={file.original_name} className={`${compact ? 'max-h-36' : 'max-h-80'} border-2 border-ink`} />
                    ) : null}
                    {file.kind === 'video' ? (
                        <video src={file.url} controls className={`${compact ? 'max-h-36' : 'max-h-80'} w-full border-2 border-ink`} />
                    ) : null}
                    {file.kind === 'pdf' || (file.kind !== 'image' && file.kind !== 'video') ? (
                        <a href={file.url} className="font-bold" target="_blank" rel="noreferrer">{file.original_name}</a>
                    ) : null}
                </li>
            ))}
        </ul>
    );
}
