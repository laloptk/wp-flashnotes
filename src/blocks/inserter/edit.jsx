import CardsNotesSearch from '../../components/cards-notes-search';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ColorPalette } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const Edit = ({ attributes, setAttributes }) => {
	const { 
    id, 
    title, 
    answers_json, 
    content, 
    borderRadius,
    shadow,
    borderColor,
    backgroundColor,
    padding,
    margin
  } = attributes;

  const blockProps = useBlockProps({
    style: {
      borderRadius: `${borderRadius}px`,
      boxShadow: shadow
          ? `0 ${shadow}px ${2 * shadow}px rgba(0,0,0,0.1)`
          : 'none',
      border: `1px solid ${borderColor}`,
      backgroundColor,
      padding: `${padding}px`,
      margin: `${margin}px`
    }
  });

	const handleSearchOnChange = (selectedItem) => {
		setAttributes({
			id: selectedItem?.id ?? '',
			title: selectedItem?.question ?? selectedItem?.title ?? '',
			answers_json: selectedItem?.answers_json ?? '',
			content: selectedItem?.explanation ?? '',
		});
	};

	return (
    <div {...blockProps}>
      <InspectorControls>
        <PanelBody
          title={__('Card Controls', 'wp-flashnotes')}
        >
          <div>{`Selected Item: ${title || ''}`}</div>
          <CardsNotesSearch
            itemType="cards"
            onChange={handleSearchOnChange}
          />
        </PanelBody>
        <PanelBody title={__('Card Style', 'wp-flashnotes')} initialOpen={true}>
          <RangeControl
              label={__('Border Radius', 'wp-flashnotes')}
              value={borderRadius}
              onChange={(value) => setAttributes({ borderRadius: value })}
              min={0}
              max={24}
          />
          <RangeControl
              label={__('Shadow Strength', 'wp-flashnotes')}
              value={shadow}
              onChange={(value) => setAttributes({ shadow: value })}
              min={0}
              max={10}
          />
          <p>{__('Border Color', 'wp-flashnotes')}</p>
          <ColorPalette
              value={borderColor}
              onChange={(value) => setAttributes({ borderColor: value })}
          />
          <p>{__('Background Color', 'wp-flashnotes')}</p>
          <ColorPalette
              value={backgroundColor}
              onChange={(value) => setAttributes({ backgroundColor: value })}
          />
          <RangeControl
              label={__('Padding', 'wp-flashnotes')}
              value={padding}
              onChange={(value) => setAttributes({ padding: value })}
              min={0}
              max={64}
          />
          <RangeControl
              label={__('Margin', 'wp-flashnotes')}
              value={margin}
              onChange={(value) => setAttributes({ margin: value })}
              min={0}
              max={64}
          />
        </PanelBody>
      </InspectorControls>
      <div>
        {
          title 
          ? <>
              <h3>{title}</h3>
              <div>{content}</div>
              <div>{answers_json}</div>
            </>
          : <>
              <h3>{__('Question', 'wp-flashnotes')}</h3>
              <div>{__('Answer', 'wp-flashnotes')}</div>
              <div>{__('Explanation', 'wp-flashnotes')}</div>
            </>
        }
      </div>
    </div>
	);
};

export default Edit;
