import { useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as blockEditorStore, useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import { v4 as uuidv4 } from 'uuid';
import { normalizeText } from '../../utils';

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

  const fnMessage = useSelect( 
    (select) => select('wpflashnotes').getMessage(), 
    [] 
  );

  console.log(fnMessage);

  const { setMessage } = useDispatch('wpflashnotes');

  setMessage('This message was updated in state');

  console.log(fnMessage);

  useEffect(() => {
    if (!childBlocks.length) return;

    let question = '';
    let answer = '';
    let explanation = '';

    childBlocks.forEach((child) => {
      if (child.name === 'wpfn/slot') {
        const role = child.attributes.role;

        // Inspect first inner block of this slot
        const firstInner = child.innerBlocks[0];
        const raw = firstInner?.attributes?.content;
        // Avoids passing an object to the attributes instead of a string
        const text = normalizeText(raw);

        if (role === 'question') {
          question = text;
        } else if (role === 'answer') {
          answer = text;
        } else if (role === 'explanation') {
          explanation = text;
        }
      }
    });

    setAttributes({ question, answers_json: [answer], explanation });
  }, [childBlocks, setAttributes]);

  return (
    <div {...blockProps}>
      <InnerBlocks
        template={[
          [ 'wpfn/slot', { role: 'question' } ],
          [ 'wpfn/slot', { role: 'answer', templateLock: 'all' } ],
          [ 'wpfn/slot', { role: 'explanation' } ]
        ]}
        templateLock="all"
        allowedBlocks={[ 'wpfn/slot' ]}
      />
    </div>
  );
}

