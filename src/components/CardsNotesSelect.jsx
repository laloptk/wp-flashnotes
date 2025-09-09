import { Popover, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import useSearch from '../hooks/useSearch';
import SafeHTMLContent from './SafeHTMLContent';

const CardsNotesSelect = ( { itemType, searchTerm, onChange, isOpen } ) => {
	const [ anchor, setAnchor ] = useState( null );
	const [ anchorWidth, setAnchorWidth ] = useState( null );
	const anchorRef = useRef();

	const { results, error, status } = useSearch(
		{ per_page: 10, s: searchTerm || '' },
		itemType,
		300
	);

	// Save anchor node and its width
	useEffect( () => {
		if ( anchorRef.current ) {
			setAnchor( anchorRef.current );
			setAnchorWidth( anchorRef.current.offsetWidth );
		}
	}, [ isOpen ] ); // recalc when opening

	const handleClick = ( item ) => {
		onChange( item );
	};

	return (
		<div
			ref={ anchorRef }
			style={ { position: 'relative', width: '100%' } }
		>
			{ anchor && isOpen && (
				<Popover
					anchor={ anchor }
					position="bottom left"
					className="wpfn-fullwidth-popover"
					focusOnMount={ false }
				>
					<div
						className="wpfn-popover-inner"
						style={ { minWidth: anchorWidth, padding: '0 15px' } }
					>
						<div className="wpfn-popover-content">
							{ status === 'success' && searchTerm !== '' ? (
								<ul>
									{ results?.items.map( ( item ) => (
										<li
											key={ item.id }
											onClick={ () =>
												handleClick( item )
											}
											style={ { cursor: 'pointer' } }
										>
											<SafeHTMLContent
												content={
													item.question ?? item.title
												}
											/>
										</li>
									) ) }
								</ul>
							) : status === 'loading' ? (
								<Spinner />
							) : error ? (
								<p>Error: { error }</p>
							) : null }
						</div>
					</div>
				</Popover>
			) }
		</div>
	);
};

export default CardsNotesSelect;
