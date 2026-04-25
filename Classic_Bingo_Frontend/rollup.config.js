import typescript from '@rollup/plugin-typescript';
import { nodeResolve } from '@rollup/plugin-node-resolve';

export default {
  input: 'js/app.ts',
  output: {
    file: 'js/app.js',
    format: 'es', 
    sourcemap: true
  },
  plugins: [
    nodeResolve(),
    typescript({ tsconfig: './tsconfig.json' })
  ]
};