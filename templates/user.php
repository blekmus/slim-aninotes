<main class="user">
    <header class="title-header">
        <h1 class="title">
            <?= $username ?>'s Notes
        </h1>
        <p><a href="/">Back</a></p>

    </header>

    <?php if (count($entries) == 0): ?>
        <div class="not-found">
            <p>This user hasn't written any notes as of yet</p>
            <img src="https://media.tenor.com/xsDhHrBrMcYAAAAM/frieren-sousou-no-frieren.gif" alt="not found">
        </div>
    <?php else: ?>
        <?php foreach ($entries as $entry): ?>
            <article>
                <figure><img src="<?= $entry['cover'] ?>" alt="cover" loading="lazy"></figure>
                <header>
                    <h2>
                        <?= $entry['title'] ?>
                    </h2>
                </header>
                <section>
                    <p>
                        <?= $entry['notes'] ?>
                    </p>
                </section>
                <footer>
                    <p>
                        <?= $entry['note_words'] ?> words <strong>·</strong>
                        <?= $entry['date_string'] ?>

                        <?php if (isset($editable)): ?>
                            <strong>·</strong>
                            <a href="/edit/<?= $entry['id'] ?>">Edit</a>
                        <?php endif; ?>
                    </p>
                </footer>
            </article>
        <?php endforeach ?>
    <?php endif; ?>
</main>