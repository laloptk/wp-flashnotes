import { useEffect, useRef, useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import ResourceAPIService from '../ResourcesAPIService';
import { addQueryArgs } from '@wordpress/url';

const useSearch = ( args = {}, blockType = 'cards', debounceMs = 300 ) => {
	const {
		setQuery,
		setBlockType,
		setStatus,
		setError,
		setResults,
		setCacheEntry,
	} = useDispatch( 'wpflashnotes' );

	const cacheKey = useMemo(
		() => addQueryArgs( blockType, args ),
		[ args, blockType ]
	);

	const { results, error, status, cacheEntries } = useSelect(
		( select ) => {
			const store = select( 'wpflashnotes' );
			return {
				results: store.getResults(),
				error: store.getError(),
				status: store.getStatus(),
				cacheEntries: store.getCache(),
			};
		},
		[ cacheKey ]
	);

	const debounceTimer = useRef( null );
	const abortController = useRef( null );

	useEffect( () => {
		setQuery( cacheKey );
		setBlockType( blockType );

		// Clear any pending debounce
		if ( debounceTimer.current ) {
			clearTimeout( debounceTimer.current );
		}

		// Abort any in-flight request
		if ( abortController.current ) {
			abortController.current.abort();
		}

		debounceTimer.current = setTimeout( async () => {
			try {
				setStatus( 'loading' );

				// Use cache if available
				if ( cacheEntries[ cacheKey ] ) {
					setResults( cacheEntries[ cacheKey ] );
					setStatus( 'success' );
					return;
				}

				// New controller for this request
				abortController.current = new AbortController();

				const api = new ResourceAPIService( blockType );
				const data = await api.get( args, {
					signal: abortController.current.signal,
				} );

				setResults( data );
				setCacheEntry( cacheKey, data );
				setStatus( 'success' );
			} catch ( e ) {
				if ( e.name === 'AbortError' ) {
					return;
				}
				setError( e.message );
				setStatus( 'error' );
			}
		}, debounceMs );

		// Cleanup on unmount
		return () => {
			if ( debounceTimer.current ) {
				clearTimeout( debounceTimer.current );
			}
			if ( abortController.current ) {
				abortController.current.abort();
			}
		};
	}, [ cacheKey ] );

	return { results, error, status };
};

export default useSearch;
