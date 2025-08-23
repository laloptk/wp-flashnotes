import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

class ResourceAPIService {
    
    constructor(type) {
        this.type = type;
        this.namespace = WPFlashNotes.apiNamespace;
        this.path = `${this.namespace}/${type}`;
    }
    
    request(args = {}) {   
        return apiFetch(args);
    }

    get(query_params = {}) {
        const args = {
            path: addQueryArgs(this.path, query_params),
            method: 'GET',
        }
        
        return this.request(args);
    }

    list() {

    }

    update(item_id, body = {}) {
        if(Object.keys(body).length === 0) {
            throw new Error('Body cannot be empty');
        }

        const args = {
            path: `${this.path}/${item_id}`,
            method: 'PUT',
            data: body,
        }

        if(this.isValidId(item_id) && Object.keys(body).length > 0) {
            return this.request(args);
        }
    }

    remove(item_id) {
        const args = {
            path: addQueryArgs(`${this.path}/${item_id}`, {hard: 1}),
            method: 'DELETE',
        }

        if(this.isValidId(item_id)) {
            return this.request(args);
        }
    }

    create(body = {}) {
        if(Object.keys(body).length === 0) {
            throw new Error('Body cannot be empty');
        }

        const args = {
            path: this.path,
            method: 'POST',
            data: body,
        }

        return this.request(args);
    }

    isValidId(id) {
        const check_id = parseInt(id);
        if(Number.isInteger(check_id) && check_id > 0) {
            return true;
        }

        return false;
    }
}