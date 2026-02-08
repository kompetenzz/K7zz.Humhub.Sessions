(function ($) {

    function slugify(text) {
        return text
            .toLowerCase()
            .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
            .replace(/[^a-z0-9\- ]/g, '')
            .replace(/\s+/g, '-')
            .replace(/\-+/g, '-')
            .replace(/^\-+|\-+$/g, '');
    }

    function initSlugify() {
        $('[data-slugify]').each(function () {
            var slugInput = $(this);
            if (slugInput.data('slugify-bound')) return;
            slugInput.data('slugify-bound', true);

            var titleSelector = slugInput.data('slugify-title-selector') || '#title';
            var autogenerate = slugInput.data('slugify-autogenerate') !== false;
            var titleInput = $(titleSelector);

            if (!titleInput.length) return;

            titleInput.on('input.slugify', function () {
                if (!autogenerate) return;
                slugInput.val(slugify(titleInput.val()));
            });

            if (autogenerate && slugInput.val().trim() === '') {
                slugInput.val(slugify(titleInput.val()));
            }
        });
    }

    $(document).on('humhub:ready', initSlugify);
    $(document).on('pjax:end', initSlugify);

})(jQuery);
