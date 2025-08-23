// src/blocks/note/edit.jsx
import { useBlockProps, RichText } from '@wordpress/block-editor';

export default function Edit({ attributes, setAttributes }) {
  const { title = '', contentDraft = '' } = attributes;
  const blockProps = useBlockProps({ className: 'wpfn-note' });

  return (
    <div {...blockProps}>
      <RichText
        tagName="h4"
        placeholder="Note title…"
        value={title}
        onChange={(val) => setAttributes({ title: val })}
        className="wpfn-note__title"
      />
      <RichText
        tagName="div"
        placeholder="Write your note…"
        value={contentDraft}
        onChange={(val) => setAttributes({ contentDraft: val })}
        className="wpfn-note__content"
      />
    </div>
  );
}
