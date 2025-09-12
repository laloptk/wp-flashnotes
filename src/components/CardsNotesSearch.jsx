import { TextControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import CardsNotesSelect from './CardsNotesSelect';

const CardsNotesSearch = ( { itemType, onChange } ) => {
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ isOpen, setIsOpen ] = useState( false );

	const handleSelectChange = ( selectedItem ) => {
		onChange( selectedItem );
		setIsOpen( false ); // close popover when an item is chosen
	};

	return (
		<div>
			<TextControl
				label="Search Cards"
				value={ searchTerm }
				onChange={ ( val ) => setSearchTerm( val ) }
				onFocus={ () => setIsOpen( true ) }
				onBlur={ () => {
					setTimeout( () => setIsOpen( false ), 150 );
				} }
				placeholder="Type to search..."
				autoComplete="off"
				__next40pxDefaultSize
				__nextHasNoMarginBottom
			/>
			<CardsNotesSelect
				itemType={ itemType }
				searchTerm={ searchTerm }
				onChange={ handleSelectChange }
				isOpen={ isOpen }
			/>
		</div>
	);
};

export default CardsNotesSearch;
