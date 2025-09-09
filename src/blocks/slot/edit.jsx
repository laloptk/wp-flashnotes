import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import { serialize } from '@wordpress/blocks';

const ALLOWED_MAP = {
	question: [ 'core/paragraph', 'core/heading', 'core/list' ],
	explanation: [ 'core/paragraph', 'core/list', 'core/image' ],
	note: [ 'core/paragraph', 'core/heading', 'core/quote' ],
	answer: [ 'core/paragraph', 'core/list' ],
};

export default function Edit( { clientId, attributes, setAttributes } ) {
	const { role, templateLock = false, content } = attributes;
	const blockProps = useBlockProps( {
		className: `wpfn-slot role-${ role }`,
	} );

	// Observe inner blocks
	const childBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);

	useEffect( () => {
		if ( ! childBlocks.length ) {
			return;
		}

		// Serialize all child blocks into HTML
		const html = serialize( childBlocks );

		if ( html !== content ) {
			setAttributes( { content: html } );
		}
	}, [ childBlocks, content, setAttributes ] );

	return (
		<div { ...blockProps }>
			<h4>{ role.charAt( 0 ).toUpperCase() + role.slice( 1 ) }</h4>
			<InnerBlocks
				allowedBlocks={ ALLOWED_MAP[ role ] || [] }
				template={ [
					[ 'core/paragraph', { placeholder: `Enter ${ role }…` } ],
				] }
				templateLock={ templateLock }
				renderAppender={ InnerBlocks.DefaultBlockAppender }
			/>
		</div>
	);
}
