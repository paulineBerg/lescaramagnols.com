ALTER TABLE {{table:blog_articles}}
    ADD COLUMN `subcategory` VARCHAR(191) NULL AFTER `category`,
    ADD KEY `idx_blog_articles_subcategory` (`subcategory`);
