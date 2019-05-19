<button class="fbrev-connect"><?php echo fbrev_i('Log In with Facebook'); ?></button>

<div class="fbrev-pages"></div>

<div class="form-group">
    <div class="col-sm-12">
        <input type="text" id="<?php echo $this->get_field_id('page_name'); ?>" name="<?php echo $this->get_field_name('page_name'); ?>" value="<?php echo $page_name; ?>" class="form-control fbrev-page-name" placeholder="<?php echo fbrev_i('Page Name'); ?>" readonly />
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <input type="text" id="<?php echo $this->get_field_id('page_id'); ?>" name="<?php echo $this->get_field_name('page_id'); ?>" value="<?php echo $page_id; ?>" class="form-control fbrev-page-id" placeholder="<?php echo fbrev_i('Page ID'); ?>" readonly />
    </div>
</div>

<input type="hidden" id="<?php echo $this->get_field_id('page_access_token'); ?>" name="<?php echo $this->get_field_name('page_access_token'); ?>" value="<?php echo $page_access_token; ?>" class="form-control fbrev-page-token" placeholder="<?php echo fbrev_i('Access token'); ?>" readonly />

<?php if (isset($title)) { ?>
<div class="form-group">
    <div class="col-sm-12">
        <label><?php echo fbrev_i('Title'); ?></label>
        <input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $title; ?>" class="form-control" />
    </div>
</div>
<?php } ?>

<div class="form-group">
    <div class="col-sm-12">
        <label><?php echo fbrev_i('Pagination'); ?></label>
        <input type="text" id="<?php echo $this->get_field_id('pagination'); ?>" name="<?php echo $this->get_field_name('pagination'); ?>" value="<?php echo $pagination; ?>" class="form-control"/>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label><?php echo fbrev_i('Characters before \'read more\' link'); ?></label>
        <input type="text" id="<?php echo $this->get_field_id('text_size'); ?>" name="<?php echo $this->get_field_name('text_size'); ?>" value="<?php echo $text_size; ?>" class="form-control"/>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label for="<?php echo $this->get_field_id('max_width'); ?>"><?php echo fbrev_i('Widget width'); ?></label>
        <input id="<?php echo $this->get_field_id('max_width'); ?>" name="<?php echo $this->get_field_name('max_width'); ?>" value="<?php echo $max_width; ?>" class="form-control" type="text" />
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label for="<?php echo $this->get_field_id('max_height'); ?>"><?php echo fbrev_i('Widget height'); ?></label>
        <input id="<?php echo $this->get_field_id('max_height'); ?>" name="<?php echo $this->get_field_name('max_height'); ?>" value="<?php echo $max_height; ?>" class="form-control" type="text" />
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label>
            <input id="<?php echo $this->get_field_id('centered'); ?>" name="<?php echo $this->get_field_name('centered'); ?>" type="checkbox" value="1" <?php checked('1', $centered); ?> class="form-control" />
            <?php echo fbrev_i('Place by center (only if Width is set)'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label>
            <input id="<?php echo $this->get_field_id('disable_user_link'); ?>" name="<?php echo $this->get_field_name('disable_user_link'); ?>" type="checkbox" value="1" <?php checked('1', $disable_user_link); ?> class="form-control"/>
            <?php echo fbrev_i('Disable user profile links'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label>
            <input id="<?php echo $this->get_field_id('dark_theme'); ?>" name="<?php echo $this->get_field_name('dark_theme'); ?>" type="checkbox" value="1" <?php checked('1', $dark_theme); ?> class="form-control" />
            <?php echo fbrev_i('Dark background'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label>
            <input id="<?php echo $this->get_field_id('open_link'); ?>" name="<?php echo $this->get_field_name('open_link'); ?>" type="checkbox" value="1" <?php checked('1', $open_link); ?> class="form-control" />
            <?php echo fbrev_i('Open links in new Window'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label>
            <input id="<?php echo $this->get_field_id('nofollow_link'); ?>" name="<?php echo $this->get_field_name('nofollow_link'); ?>" type="checkbox" value="1" <?php checked('1', $nofollow_link); ?> class="form-control" />
            <?php echo fbrev_i('User no follow links'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label>
            <input id="<?php echo $this->get_field_id('show_success_api'); ?>" name="<?php echo $this->get_field_name('show_success_api'); ?>" type="checkbox" value="1" <?php checked('1', $show_success_api); ?> class="form-control" />
            <?php echo fbrev_i('Show last success API response'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label>
            <input id="<?php echo $this->get_field_id('lazy_load_img'); ?>" name="<?php echo $this->get_field_name('lazy_load_img'); ?>" type="checkbox" value="1" <?php checked('1', $lazy_load_img); ?> class="form-control" />
            <?php echo fbrev_i('Lazy load images'); ?>
        </label>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <?php echo fbrev_i('Cache data'); ?>
        <select id="<?php echo $this->get_field_id('cache'); ?>" name="<?php echo $this->get_field_name('cache'); ?>" class="form-control">
            <option value="1" <?php selected('1', $cache); ?>><?php echo fbrev_i('1 Hour'); ?></option>
            <option value="3" <?php selected('3', $cache); ?>><?php echo fbrev_i('3 Hours'); ?></option>
            <option value="6" <?php selected('6', $cache); ?>><?php echo fbrev_i('6 Hours'); ?></option>
            <option value="12" <?php selected('12', $cache); ?>><?php echo fbrev_i('12 Hours'); ?></option>
            <option value="24" <?php selected('24', $cache); ?>><?php echo fbrev_i('1 Day'); ?></option>
            <option value="48" <?php selected('48', $cache); ?>><?php echo fbrev_i('2 Days'); ?></option>
            <option value="168" <?php selected('168', $cache); ?>><?php echo fbrev_i('1 Week'); ?></option>
        </select>
    </div>
</div>

<div class="form-group">
    <div class="col-sm-12">
        <label><?php echo fbrev_i('Reviews limit'); ?></label>
        <input id="<?php echo $this->get_field_id('api_ratings_limit'); ?>" name="<?php echo $this->get_field_name('api_ratings_limit'); ?>" value="<?php echo $api_ratings_limit; ?>" type="text" placeholder="By default: <?php echo FBREV_API_RATINGS_LIMIT; ?>" class="form-control"/>
    </div>
</div>

<div class="form-group">
    <div class="rplg-pro">
        <?php echo fbrev_i('Try more features in the Business version: '); ?>
        <a href="https://richplugins.com/business-reviews-bundle-wordpress-plugin" target="_blank">
            <?php echo fbrev_i('Upgrade to Business'); ?>
        </a>
    </div>
</div>

<input id="<?php echo $this->get_field_id('view_mode'); ?>" name="<?php echo $this->get_field_name('view_mode'); ?>" type="hidden" value="list" />