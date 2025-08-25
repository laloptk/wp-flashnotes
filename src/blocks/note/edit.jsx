import { useBlockProps, RichText } from '@wordpress/block-editor';
import { TextareaControl, ToggleControl, TextControl } from '@wordpress/components';
import useFetch from '../../hooks/useFetch';

export default function Edit({ attributes, setAttributes }) {
  const { title, contentDraft, hide } = attributes;
  const blockProps = useBlockProps({ className: 'wpfn-note' });
  const { data, loading, error } = useFetch('notes');

  return (
    <div {...blockProps}>
      <TextControl
        label="Title"
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
      <ToggleControl
        label="Hide Note In Frontend"
        checked={hide}
        onChange={(val) => setAttributes({ hide: val })}
      />
    </div>
  );
}
