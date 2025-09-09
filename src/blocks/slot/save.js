import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	return (
		<div { ...useBlockProps.save() }>
			<InnerBlocks.Content />
			{ '' /* fuerza innerContent vacío si no hay hijos */ }
		</div>
	);
}
