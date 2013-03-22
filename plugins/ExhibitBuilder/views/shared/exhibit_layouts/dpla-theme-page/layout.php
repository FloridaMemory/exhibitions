<? if ($attachment = exhibit_builder_page_attachment(2)): ?>
    <div class="slide-Container">
        <div class="slidegallery">
            <div class="slides">
            <section id="slideshow">
                <? echo dpla_attachment_markup($attachment, array('imageSize' => 'fullsize'), array('class' => 'permalink')); ?>
            </section>
            </div>
            <? if ($gallery = dpla_thumbnail_gallery(2, 7, array('class'=>'permalink'))): ?>
                <div id="thumbs"><?= $gallery ?></div>
            <? endif; ?>
        </div>
        <a href="#itemDetailsBox" class="show-item-details cboxElement"></a>
    </div>

    <div class="overlay">
        <div id="itemDetailsBox">
            <div id="cboxClose" class="pclose">&times;</div>
            <h1>Ojibwa beaded velvet loincloths</h1>
            <article>
                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent ultricies libero nec velit sollicitudin eget ornare (description)</p>

                <div class="table">
                    <ul>
                        <li><h6>Creator</h6></li>
                        <li>Text</li>
                    </ul>
                    <ul>
                        <li><h6>Created Date</h6></li>
                        <li>Text</li>
                    </ul>
                    <ul>
                        <li><h6>Owning Institution</h6></li>
                        <li>Text</li>
                    </ul>
                    <ul>
                        <li><h6>Provider</h6></li>
                        <li>Text</li>
                    </ul>
                    <ul>
                        <li><h6>Publisher</h6></li>
                        <li>Text</li>
                    </ul>
                </div>

            </article>
        </div>
    </div>
<? endif; ?>

<?php echo exhibit_builder_page_text(2); ?>

<ul class="prevNext">
    <?php // TODO: Define first and last pages ?>
    <? if ($nextLink = dpla_link_to_next_page('Next »')): ?>
        <li class="btn"><?= $nextLink ?></li>
    <? endif; ?>
    <li><?= dpla_page_position(); ?></li>
</ul>