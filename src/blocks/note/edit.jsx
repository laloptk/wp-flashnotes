import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import {
	store as blockEditorStore,
	useBlockProps,
	InnerBlocks,
} from '@wordpress/block-editor';
import { v4 as uuidv4 } from 'uuid';
import {
	VisibilityControls,
	SpacingControls,
	StyleControls,
} from '@wpfn/components';
import { normalizeStyle } from '@wpfn/styles';
import { normalizeText } from '../../utils';

export default function Edit( { clientId, attributes, setAttributes } ) {
	const {
		block_id,
		title,
		content,
		margin,
		padding,
		border,
		borderRadius,
		backgroundColor,
		hidden,
	} = attributes;

	const style = {
		...( backgroundColor && { backgroundColor } ),
		...( normalizeStyle( 'border', border ) || {} ),
		...( normalizeStyle( 'margin', margin ) || {} ),
		...( normalizeStyle( 'padding', padding ) || {} ),
		...( normalizeStyle( 'borderRadius', borderRadius ) || {} ),
	};

	const blockProps = useBlockProps( {
		className: 'wpfn-note',
		style,
	} );

	// Assign UUID once
	useEffect( () => {
		if ( ! block_id ) {
			setAttributes( { block_id: uuidv4() } );
		}
	}, [ block_id, setAttributes ] );

	// Watch direct children (slots)
	const childBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ]
	);

	// Sync slot content → attributes
	useEffect( () => {
		if ( ! childBlocks.length ) {
			return;
		}

		let nextTitle = '';
		let nextContent = '';

		childBlocks.forEach( ( child ) => {
			if ( child.name === 'wpfn/slot' ) {
				const { role, content: raw } = child.attributes;
				if ( role === 'title' ) {
					nextTitle = normalizeText( raw || '' );
				} else if ( role === 'content' ) {
					nextContent = raw ? normalizeText( raw ) : '';
				}
			}
		} );

		// Only update if something actually changed
		if ( nextTitle !== title || nextContent !== content ) {
			setAttributes( {
				title: nextTitle,
				content: nextContent,
			} );
		}
	}, [ childBlocks, title, content, setAttributes ] );

	return (
		<>
			<VisibilityControls
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
			<SpacingControls
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
			<StyleControls
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>

			<div { ...blockProps }>
				<InnerBlocks
					template={ [
						[ 'wpfn/slot', { role: 'title' } ],
						[ 'wpfn/slot', { role: 'content' } ],
					] }
					allowedBlocks={ [ 'wpfn/slot' ] }
					templateLock="all"
				/>
			</div>
		</>
	);
}
