import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const ALLOWED_MAP = {
  question: [ 'core/paragraph', 'core/heading', 'core/list' ],
  explanation: [ 'core/paragraph', 'core/list', 'core/image' ],
  note: [ 'core/paragraph', 'core/heading', 'core/quote' ]
};

export default function Edit({ attributes }) {
  const { role, templateLock = false } = attributes;
  const blockProps = useBlockProps({ className: `wpfn-slot role-${role}` });

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
