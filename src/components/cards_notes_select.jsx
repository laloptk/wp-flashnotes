// CardsNotesSelect.js
import { Popover, Spinner } from '@wordpress/components';
import useSearch from '../hooks/useSearch';
import { v4 as uuidv4 } from 'uuid';

const CardsNotesSelect = ({ itemType, searchTerm, onChange }) => {
    const { results, error, status } = useSearch(
        { per_page: 10, s: searchTerm || '' },
        itemType,
        300
    );

    console.log(results);

    const handleClick = (item) => {
        onChange(item);
    };

    return (
        <Popover>
            {status === 'success' ? (
                <ul>
                    {results?.items.map((item) => (
                        <li
                            key={uuidv4()}
                            onClick={() => handleClick(item)}
                        >
                            {console.log(`This is the item: ${item}`)}
                            {item.question ?? item.title}
                        </li>
                    ))}
                </ul>
            ) : status === 'loading' ? (
                <Spinner />
            ) : error ? (
                <p>Error: {error}</p>
            ) : null}
        </Popover>
    );
};

export default CardsNotesSelect;
