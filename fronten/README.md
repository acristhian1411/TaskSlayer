# sv

Everything you need to build a Svelte project, powered by [`sv`](https://github.com/sveltejs/cli).

## Creating a project

If you're seeing this, you've probably already done this step. Congrats!

```sh
# create a new project
npx sv create my-app
```

To recreate this project with the same configuration:

```sh
# recreate this project
npx sv@0.15.1 create --template minimal --no-types --install npm fronten
```

## Developing

Once you've created a project and installed dependencies with `npm install` (or `pnpm install` or `yarn`), start a development server:

```sh
npm run dev

# or start the server and open the app in a new browser tab
npm run dev -- --open
```

## Building

To create a production version of your app:

```sh
npm run build
```

You can preview the production build with `npm run preview`.

> To deploy your app, you may need to install an [adapter](https://svelte.dev/docs/kit/adapters) for your target environment.

## LM Studio integration

The Forge page now generates quests through an OpenAI-compatible LM Studio endpoint:

1. Start LM Studio local server and load a chat model.
2. Copy `.env.example` to `.env` inside `fronten/`.
3. Set `LMSTUDIO_BASE_URL` (default `http://127.0.0.1:1234/v1`).
4. Set `LMSTUDIO_MODEL` to the model identifier exposed by LM Studio.
5. Set `LMSTUDIO_API_KEY` only if your LM Studio server requires it.

Generation runs through `src/routes/forge/generate/+server.js`, so browser CORS and client memory limits are avoided.
