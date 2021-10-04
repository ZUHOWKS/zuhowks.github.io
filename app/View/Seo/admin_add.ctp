<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header with-border">
                    <h3 class="card-title"><?= $Lang->get('SEO__ADD_PAGE') ?></h3>
                </div>
                <div class="card-body">
                    <form method="post" data-ajax="true" data-upload-image="true"
                          data-redirect-url="<?= $this->Html->url(['controller' => 'seo', 'action' => 'index', 'admin' => 'true']) ?>">
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label><?= $Lang->get('SEO__PAGE') ?></label>
                                    <br>
                                    <em><?= $Lang->get('SEO__PAGE_DESC') ?></em>
                                    <input type="text" class="form-control" name="page">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label><?= $Lang->get('SEO__FORM_TITLE') ?></label>
                                    <br>
                                    <em><?= $Lang->get('SEO__KEEP_EMPTY') ?></em>
                                    <input type="text" class="form-control" name="title">

                                    <small><b>{TITLE}</b> = <?= $Lang->get('SEO__FORM_TITLE_DESC_T') ?>
                                        <br><b>{WEBSITE_NAME}</b> = <?= $Lang->get('SEO__FORM_TITLE_DESC_W') ?></small>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label><?= $Lang->get('SEO__FORM_DESCRIPTION') ?></label>
                                    <br>
                                    <em><?= $Lang->get('SEO__KEEP_EMPTY') ?></em>
                                    <input type="text" class="form-control" name="description">
                                </div>
                            </div>

                            <div class="col-12">
                                <hr>
                            </div>

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label><?= $Lang->get('SEO__FORM_FAVICON') ?></label><em> <?= $Lang->get('SEO__FORM_FAVICON_DESC') ?></em>
                                    <br>
                                    <em><?= $Lang->get('SEO__KEEP_EMPTY') ?></em>
                                    <?= $this->element('form.input.upload.img', ['filename' => "favicon.png"]); ?>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label><?= $Lang->get('SEO__FORM_IMG_URL') ?></label><em> <?= $Lang->get('SEO__FORM_IMG_URL_DESC') ?></em>
                                    <br>
                                    <em><?= $Lang->get('SEO__KEEP_EMPTY') ?></em>
                                    <input type="text" class="form-control" name="img_url">
                                </div>
                            </div>

                            <div class="col-12">
                                <hr>
                            </div>

                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label><?= $Lang->get('SEO__FORM_THEME_COLOR') ?></label><em> <?= $Lang->get('SEO__FORM_THEME_COLOR_DESC') ?></em>
                                    <br>
                                    <em><?= $Lang->get('SEO__KEEP_EMPTY') ?></em>
                                    <input type="color" class="form-control" name="theme_color">
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="form-group">
                                    <label><?= $Lang->get('SEO__FORM_TWITTER_SITE') ?></label><em> <?= $Lang->get('SEO__FORM_TWITTER_SITE_DESC') ?></em>
                                    <br>
                                    <em><?= $Lang->get('SEO__KEEP_EMPTY') ?></em>
                                    <input type="text" class="form-control" name="twitter_site">
                                </div>
                            </div>
                        </div>


                        <div class="float-right">
                            <a href="<?= $this->Html->url(['controller' => 'seo', 'action' => 'index', 'admin' => true]) ?>"
                               class="btn btn-default"><?= $Lang->get('GLOBAL__CANCEL') ?></a>
                            <button class="btn btn-primary" type="submit"><?= $Lang->get('GLOBAL__SUBMIT') ?></button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</section>
