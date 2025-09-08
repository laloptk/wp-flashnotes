import { Popover, Spinner } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import useSearch from '../hooks/useSearch';

const CardsNotesSelect = ({ itemType, searchTerm, onChange }) => {
    const [anchor, setAnchor] = useState(null);
    const anchorRef = useRef();

    const { results, error, status } = useSearch(
        { per_page: 10, s: searchTerm || '' },
        itemType,
        300
    );

    useEffect(() => {
        console.log("Results changed:", results);
    }, [results]);

    const handleClick = (item) => {
        onChange(item);
    };

    // Update anchor element when component mounts
    useEffect(() => {
        if (anchorRef.current) {
            setAnchor(anchorRef.current);
        }
    }, []);

    return (
        <div ref={anchorRef} style={{ position: 'relative' }}>
            {anchor && (
                <Popover
                    anchor={anchor}
                    placement="bottom-start"
                    focusOnMount="firstElement"
                    flip
                    resize
                >
                    {status === 'success' ? (
                        <ul>
                            {results?.items.map((item) => (
                                <li
                                    key={item.id}
                                    onClick={() => handleClick(item)}
                                    style={{ cursor: 'pointer' }}
                                >
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
            )}
        </div>
    );
};

export default CardsNotesSelect;
