import { useState, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import CardsNotesSearch from '../../components/CardsNotesSearch';
import VisibilityControls from '../../components/controls/VisibilityControls';
import SpacingControls from '../../components/controls/SpacingControls';
import StyleControls from '../../components/controls/StyleControls';
import SafeHTMLContent from '../../components/SafeHTMLContent';
import useFetch from '../../hooks/useFetch';
import {assembleContent} from '../../utils';

const Edit = ({ attributes, setAttributes, clientId }) => {
	const { id, block_id, margin, padding, border, backgroundColor, hidden } = attributes;

	const blockProps = useBlockProps({
		className: 'wpfn-inserter',
		style: {
			marginTop: margin?.top,
			marginRight: margin?.right,
			marginBottom: margin?.bottom,
			marginLeft: margin?.left,
			paddingTop: padding?.top,
			paddingRight: padding?.right,
			paddingBottom: padding?.bottom,
			paddingLeft: padding?.left,
			borderTop: border?.top?.width
				? `${border.top.width} solid ${border.top.color || '#ddd'}`
				: undefined,
			borderRight: border?.right?.width
				? `${border.right.width} solid ${border.right.color || '#ddd'}`
				: undefined,
			borderBottom: border?.bottom?.width
				? `${border.bottom.width} solid ${border.bottom.color || '#ddd'}`
				: undefined,
			borderLeft: border?.left?.width
				? `${border.left.width} solid ${border.left.color || '#ddd'}`
				: undefined,
			borderRadius: border?.radius,
			backgroundColor,
			display: hidden ? 'none' : undefined,
		},
	});

	// Set persistent block_id once
	useEffect(() => {
		if (!block_id) setAttributes({ block_id: clientId });
	}, [block_id, clientId, setAttributes]);

	const [content, setContent] = useState('');

	// Rehydrate from API when id is present (editor reload)
	const { data, loading, error } = useFetch('cards', { id });

	useEffect(() => {
		if (!data?.items) return;

		const selected = Object.values(data.items).find(
			(item) => String(item.id) === String(id)
		);

		if (!selected) return;

		if (!content || String(selected.id) === String(id)) {
			setContent(assembleContent(selected));
		}
	}, [data, id]);

	// Immediate hydration on selection
	const handleSearchOnChange = (selectedItem) => {
		if (!selectedItem) return;
		setAttributes({ id: selectedItem.id });
		setContent(assembleContent(selectedItem));
	};

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__('Search Controls', 'wp-flashnotes')}>
					<CardsNotesSearch itemType="cards" onChange={handleSearchOnChange} />
				</PanelBody>
				<VisibilityControls attributes={attributes} setAttributes={setAttributes} />
				<SpacingControls attributes={attributes} setAttributes={setAttributes} />
				<StyleControls attributes={attributes} setAttributes={setAttributes} />
			</InspectorControls>

			<div className="wpfn-rendered-card">
				{!id && <p>{__('Select a card…', 'wp-flashnotes')}</p>}
				{id && loading && !content && <Spinner />}
				{error && <p className="error">{String(error)}</p>}
				{content && <SafeHTMLContent content={content} classes="wpfn-card-html" />}
			</div>
		</div>
	);
};

export default Edit;
