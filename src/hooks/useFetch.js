import {useState, useEffect} from '@wordpress/element';

const useFetch = () => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        const getItems = async () => {
            try {
            setLoading(true);
            //const results = await apiFetch($path);
            
            } catch(e) {
                setLoading(false);
            } finally {
                setLoading(false);
            }
        }

        getItems();
    }, []);
}