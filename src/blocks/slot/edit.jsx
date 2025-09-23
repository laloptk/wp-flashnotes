import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import { serialize } from '@wordpress/blocks';
import { Icon } from '@wordpress/icons';
import { help, commentEditLink, quote, check, heading, page } from '@wordpress/icons';

const ALLOWED_MAP = {
	question: [ 'core/paragraph', 'core/heading', 'core/list', 'core/quote' ],
	explanation: [ 'core/paragraph', 'core/list', 'core/image', 'core/heading', 'core/quote' ],
	answer: [ 'core/paragraph' ],
	title: [ 'core/heading' ],
	content: [ 'core/paragraph', 'core/list', 'core/image', 'core/heading', 'core/quote', 'core/list' ],
};

const ROLE_ICONS = {
	question: help,
	answer: check,
	explanation: commentEditLink,
	note: quote,
	title: heading,
	content: page,
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
			<div className="wpfn-slot-header">
				<Icon
					icon={ ROLE_ICONS[ role ] }
					className="wpfn-slot-icon"
					size={ 42 }
				/>
			</div>
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
