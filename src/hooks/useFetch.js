import {
	useState,
	useEffect,
	useMemo,
	useCallback,
	useRef,
} from '@wordpress/element';
import ResourcesAPIService from '../ResourcesAPIService';
import useStableQuery from './useStableQuery';

const useFetch = ( type, queryParams = {} ) => {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );

	// Memoize the API client for stability across renders
	const api = useMemo( () => new ResourcesAPIService( type ), [ type ] );
	const stableQuery = useStableQuery( queryParams );

	const abortRef = useRef( null );

	const fetch = useCallback( async () => {
		// Cancel any in-flight request
		abortRef.current?.abort?.();

		const controller = new AbortController();
		abortRef.current = controller;

		try {
			setLoading( true );
			setError( null );
			const result = await api.find( queryParams, {
				signal: controller.signal,
			} );
			setData( result );
		} catch ( e ) {
			// Ignore abort errors; surface everything else
			if ( e?.name !== 'AbortError' ) {
				setError( e?.message ?? String( e ) );
			}
		} finally {
			setLoading( false );
		}
	}, [ api, stableQuery ] ); // stringify to track param changes

	useEffect( () => {
		fetch();
		// Cleanup on unmount or deps change → abort pending request
		return () => abortRef.current?.abort?.();
	}, [ fetch ] );

	return { data, loading, error };
};

export default useFetch;
