// ResourcesAPIService.js
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

export default class ResourceAPIService {
	constructor( type ) {
		this.type = type;
		this.namespace = WPFlashNotes.apiNamespace;
		this.path = `${ this.namespace }/${ this.type }`;
	}

	request( args = {} ) {
		return apiFetch( args );
	}

	// GET /{type}?...
	get( query_params = {}, { signal } = {} ) {
		return this.request({
			path: addQueryArgs( this.path, query_params ),
			method: 'GET',
			signal,
		});
	}

	// GET /{type}/find?...
	find( query_params = {}, { signal } = {} ) {
		return this.request({
			path: addQueryArgs( `${ this.path }/find`, query_params ),
			method: 'GET',
			signal,
		});
	}

	// Optional: build args objects for useFetch (args-only variant)
	build_get_args( query_params = {} ) {
		return {
			path: addQueryArgs( this.path, query_params ),
			method: 'GET',
		};
	}

	build_find_args( query_params = {} ) {
		return {
			path: addQueryArgs( `${ this.path }/find`, query_params ),
			method: 'GET',
		};
	}

	create( body = {}, { signal } = {} ) {
		if ( Object.keys( body ).length === 0 ) throw new Error( 'Body cannot be empty' );
		return this.request({
			path: this.path,
			method: 'POST',
			data: body,
			signal,
		});
	}

	update( item_id, body = {} ) {
		if ( Object.keys( body ).length === 0 ) throw new Error( 'Body cannot be empty' );
		if ( ! this.is_valid_id( item_id ) ) return;
		return this.request({
			path: `${ this.path }/${ item_id }`,
			method: 'PUT',
			data: body,
		});
	}

	upsert(body = {}, item_id = null) {
		if ( ! this.is_valid_id( item_id ) ) {
			return this.create(body)
		} 
		
		return this.update( item_id, body );
	}

	remove( item_id ) {
		if ( ! this.is_valid_id( item_id ) ) return;
		return this.request({
			path: addQueryArgs( `${ this.path }/${ item_id }`, { hard: 1 } ),
			method: 'DELETE',
		});
	}

	is_valid_id( id ) {
		const check_id = parseInt( id, 10 );
		return Number.isInteger( check_id ) && check_id > 0;
	}
}
