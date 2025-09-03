import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore, useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const ALLOWED_MAP = {
  question: [ 'core/paragraph', 'core/heading', 'core/list' ],
  explanation: [ 'core/paragraph', 'core/list', 'core/image' ],
  note: [ 'core/paragraph', 'core/heading', 'core/quote' ]
};

export default function Edit({ clientId, attributes, setAttributes }) {
  const { role, templateLock = false, content } = attributes;
  const blockProps = useBlockProps({ className: `wpfn-slot role-${role}` });

  // Observa los inner blocks de este slot
  const childBlocks = useSelect(
    (select) => select(blockEditorStore).getBlocks(clientId),
    [clientId]
  );

  useEffect(() => {
    if (!childBlocks.length) return;

    // Toma el primer inner block
    const firstInner = childBlocks[0];
    const raw = firstInner?.attributes?.content || '';

    if (raw !== content) {
      setAttributes({ content: raw });
    }
  }, [childBlocks, content, setAttributes]);

  return (
    <div {...blockProps}>
      <h4>{ role.charAt(0).toUpperCase() + role.slice(1) }</h4>
      <InnerBlocks
        allowedBlocks={ ALLOWED_MAP[role] || [] }
        template={[
          [ 'core/paragraph', { placeholder: `Enter ${role}…` } ]
        ]}
        templateLock={ templateLock }
        renderAppender={ InnerBlocks.DefaultBlockAppender }
      />
    </div>
  );
}
