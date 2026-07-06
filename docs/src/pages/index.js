import clsx from 'clsx';
import Link from '@docusaurus/Link';
import useDocusaurusContext from '@docusaurus/useDocusaurusContext';
import Layout from '@theme/Layout';

import Heading from '@theme/Heading';
import styles from './index.module.css';

function HomepageHeader() {
  const {siteConfig} = useDocusaurusContext();
  return (
    <header className={clsx('hero hero--primary', styles.heroBanner)}>
      <div className="container">
        <Heading as="h1" className="hero__title">
          {siteConfig.title}
        </Heading>
        <p className="hero__subtitle">{siteConfig.tagline}</p>
        <p className={styles.pitch}>
          React Server Components split one program across two computers,
          connected by a one-way door for serializable data. PHP already
          lives on the server side of that door — <code>phpx-server</code>{' '}
          ports server components, Suspense streaming, and server actions
          over, nearly for free.
        </p>
        <div className={styles.buttons}>
          <Link className="button button--secondary button--lg" to="/docs/introduction">
            Get started
          </Link>
        </div>
      </div>
    </header>
  );
}

export default function Home() {
  const {siteConfig} = useDocusaurusContext();
  return (
    <Layout
      title={siteConfig.title}
      description="React Server Components ideas — server components, Suspense streaming, and server actions — ported to PHP with PHPX.">
      <HomepageHeader />
    </Layout>
  );
}
