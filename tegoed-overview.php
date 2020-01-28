<?php $value = get_post_meta( get_the_ID(), 'aanpassing', 'single' ); ?>

<div class="tegoed__row">
    <div class="tegoed__date"><?php echo get_the_date(); ?></div>
    <div class="tegoed__title"><?php the_title(); ?></div>
    <div class="tegoed__aanpassing<?php echo ($value < 0 ? ' tegoed__negatief' : ''); ?>"><?php echo $value; ?></div>
</div>
