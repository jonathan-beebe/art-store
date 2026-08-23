import type { FastifyInstance, FastifyPluginCallback } from 'fastify'

/** What Fastify reads off a plugin function: the name it prints in the plugin
 * tree, and the plugins that must already be registered when it loads. */
export type RootPluginMeta = {
  name: string
  dependencies?: readonly string[]
}

// The two symbols the `fastify-plugin` package sets. `skip-override` is the
// whole of what that package does that matters here, so the package itself
// stays out of the tree.
const SKIP_OVERRIDE = Symbol.for('skip-override')
const PLUGIN_META = Symbol.for('plugin-meta')

/**
 * A cross-cutting feature as a plugin Fastify can introspect. `skip-override`
 * hands it the root instance rather than a child of it, which is what a
 * decorator or a root hook needs — added inside an encapsulated plugin, both
 * would be invisible to every site.
 *
 * `dependencies` fails the boot when something the feature needs was never
 * registered, in place of the file position that decides it otherwise.
 */
export function rootPlugin(
  meta: RootPluginMeta,
  extend: (app: FastifyInstance) => void,
): FastifyPluginCallback {
  const plugin: FastifyPluginCallback = (app, _options, done) => {
    extend(app)
    done()
  }

  Object.defineProperty(plugin, SKIP_OVERRIDE, { value: true })
  Object.defineProperty(plugin, PLUGIN_META, { value: meta })

  return plugin
}
