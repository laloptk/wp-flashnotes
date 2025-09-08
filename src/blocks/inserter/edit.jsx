import CardsNotesSearch from '../../components/cards-notes-search';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const Edit = ({ attributes, setAttributes }) => {
	const { id, title, answer_json, content } = attributes;
  const blockProps = useBlockProps();
	const handleSearchOnChange = (selectedItem) => {
    //console.log(selectedItem);
		setAttributes({
			id: selectedItem?.id ?? '',
			title: selectedItem?.question ?? selectedItem?.title ?? '',
			answer_json: selectedItem?.answer_json ?? '',
			content: selectedItem?.explanation ?? '',
		});
	};

	return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody
          title={__('Card Controls', 'inspector-control-groups')}
        >
          <div>{`Selected Item: ${title || ''}`}</div>
          <CardsNotesSearch
            itemType="cards"
            onChange={handleSearchOnChange}
          />
        </PanelBody>
      </InspectorControls>
      <div>{'This is one of the most what'}</div>
    </div>
	);
};

export default Edit;
