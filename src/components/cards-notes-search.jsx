// CardsNotesSearch.js
import { TextControl } from '@wordpress/components'; 
import { useState, useEffect } from '@wordpress/element';
import CardsNotesSelect from './cards_notes_select';

const CardsNotesSearch = ({ itemType, onChange }) => {
    const [searchTerm, setSearchTerm] = useState('');

    const handleSelectChange = (selectedItem) => {
        onChange(selectedItem);
    };

    useEffect(() => {
        console.log("SearchTerm changed:", searchTerm);
    }, [searchTerm]);

    return (
        <div>
            <TextControl
                label="Search Cards"
                value={searchTerm}
                onChange={(val) => setSearchTerm(val)}
                placeholder="Type to search..."
                __next40pxDefaultSize
            />
            <CardsNotesSelect
                itemType={itemType}
                searchTerm={searchTerm}
                onChange={handleSelectChange}
            />
        </div>
    );
};

export default CardsNotesSearch;
