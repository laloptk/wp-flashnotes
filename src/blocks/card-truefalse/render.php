<?php
/**
 * @var array $attributes
 * @var string $content InnerBlocks content
 */

$block_id  = isset($attributes['block_id']) ? $attributes['block_id'] : '';
$stage     = isset($attributes['stage']) ? $attributes['stage'] : '';
$card_type = isset($attributes['card_type']) ? $attributes['card_type'] : 'true_false';

$wrapper_attrs = get_block_wrapper_attributes([
    'class' => 'wpfn-card ' . sanitize_html_class($card_type),
    'data-id' => $block_id,
    'data-stage' => $stage,
]);

?>
<div <?php echo $wrapper_attrs; ?>>
    <div class="wpfn-slot role-title">
    <?php echo $content; ?>

    <div class="answers">
        <fieldset>
            <legend>Select your answer</legend>

            <label>
                <input type="radio" name="<?php echo esc_attr('wpfn_' . $block_id); ?>" value="true">
                True
            </label>

            <label>
                <input type="radio" name="<?php echo esc_attr('wpfn_' . $block_id); ?>" value="false">
                False
            </label>
        </fieldset>

        <button type="button" class="wpfn-send-answer" hidden>
            Send Answer
        </button>

        <p class="wpfn-feedback" hidden></p>
    </div>
    </div>

    <div class="wpfn-slot role-content">
        <!-- optional answer area -->
    </div>
</div>
