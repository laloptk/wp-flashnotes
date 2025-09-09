import { createReduxStore, register } from '@wordpress/data';

// Always define a default state
const DEFAULT_STATE = {
	search: {
		query: '',
		blockType: 'card',
		results: [],
		status: 'idle',
		error: null,
		cache: {},
	},
};

// Form an object with the actions you want to perform
const actions = {
	setQuery: ( query ) => {
		return {
			type: 'SET_QUERY',
			query,
		};
	},
	setBlockType: ( blockType ) => {
		return {
			type: 'SET_BLOCK_TYPE',
			blockType,
		};
	},
	setStatus: ( status ) => {
		return {
			type: 'SET_STATUS',
			status,
		};
	},
	setError: ( error ) => {
		return {
			type: 'SET_ERROR',
			error,
		};
	},
	setResults: ( results ) => {
		return {
			type: 'SET_RESULTS',
			results,
		};
	},
	setCacheEntry: ( key, results ) => {
		return {
			type: 'SET_CACHE_ENTRY',
			key,
			results,
		};
	},
};

// Write a reducer to perform a selected action (pure function)
const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_QUERY':
			return {
				...state,
				search: {
					...state.search,
					query: action.query,
				},
			};
		case 'SET_BLOCK_TYPE':
			return {
				...state,
				search: {
					...state.search,
					blockType: action.blockType,
				},
			};
		case 'SET_STATUS':
			return {
				...state,
				search: {
					...state.search,
					status: action.status,
				},
			};
		case 'SET_ERROR':
			return {
				...state,
				search: {
					...state.search,
					error: action.error,
				},
			};
		case 'SET_RESULTS':
			return {
				...state,
				search: {
					...state.search,
					results: action.results,
				},
			};
		case 'SET_CACHE_ENTRY':
			return {
				...state,
				search: {
					...state.search,
					cache: {
						...state.search.cache,
						[ action.key ]: action.results,
					},
				},
			};
		default:
			return state;
	}
};

const selectors = {
	getQuery: ( state ) => {
		return state.search.query;
	},
	getBlockType: ( state ) => {
		return state.search.blockType;
	},
	getStatus: ( state ) => {
		return state.search.status;
	},
	getError: ( state ) => {
		return state.search.error;
	},
	getResults: ( state ) => {
		return state.search.results;
	},
	getCache: ( state ) => {
		return state.search.cache;
	},
};

const store = createReduxStore( 'wpflashnotes', {
	reducer,
	actions,
	selectors,
} );

register( store );
