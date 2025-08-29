import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as blockEditorStore, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { v4 as uuidv4 } from 'uuid';

export default function Edit({ clientId, attributes, setAttributes }) {
  const { block_id } = attributes;
  const blockProps = useBlockProps({ className: 'wpfn-card' });

  // Assign UUID once
  useEffect(() => {
    if (!block_id) {
      setAttributes({ block_id: uuidv4() });
    }
  }, [block_id, setAttributes]);

  // Observe direct children (slots)
  const childBlocks = useSelect(
    (select) => select(blockEditorStore).getBlocks(clientId),
    [clientId]
  );

  // Extract question + explanation from slot attributes
  useEffect(() => {
    if (!childBlocks.length) return;

    let question = '';
    let explanation = '';

    childBlocks.forEach((child) => {
      if (child.name === 'wpfn/slot') {
        if (child.attributes.role === 'question') {
          question = child.attributes.content;
        } else if (child.attributes.role === 'explanation') {
          explanation = child.attributes.content;
        }
      }
    });

    setAttributes((prev) => {
        if (prev.question !== question || prev.explanation !== explanation) {
            return { question, explanation };
        }
        return prev;
    });
  }, [childBlocks, setAttributes]);
  
  return (
    <div {...blockProps}>
      <InnerBlocks
        template={[
          [ 'wpfn/slot', { role: 'question' } ],
          [ 'wpfn/slot', { role: 'explanation' } ]
        ]}
        templateLock="all"
        allowedBlocks={[ 'wpfn/slot' ]}
      />
    </div>
  );
}

