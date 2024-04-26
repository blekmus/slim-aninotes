<main class="edit-entry">
    <article>
        <figure><img src="<?= $entry['cover'] ?>" alt="cover" loading="lazy"></figure>
        <header>
            <h2>
                Editing <?= $entry['title'] ?>
            </h2>
        </header>
        <section>
            <form action="/edit/<?= $entry['id'] ?>" method="post">
                <textarea name="note" rows="25"><?= $entry['notes'] ?></textarea>
                <button type="submit">Save</button>
            </form>
        </section>
    </article>
</main>